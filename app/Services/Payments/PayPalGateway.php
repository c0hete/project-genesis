<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentIntent;
use App\DTOs\PaymentResult;
use App\DTOs\RefundResult;
use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayPal Gateway (Global backup)
 *
 * Integration with PayPal Checkout API.
 * Global payment gateway as backup option.
 *
 * @see https://developer.paypal.com/docs/api/overview/
 */
class PayPalGateway implements PaymentGatewayInterface
{
    private string $clientId;
    private string $secret;
    private string $mode;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = config('services.payment.paypal.client_id', '');
        $this->secret = config('services.payment.paypal.secret', '');
        $this->mode = config('services.payment.paypal.mode', 'live');

        $this->baseUrl = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * {@inheritDoc}
     */
    public function createPayment(Booking $booking): PaymentIntent
    {
        $this->ensureConfigured();

        $returnUrl = route('bookings.payment.callback', ['booking' => $booking->id]);
        $cancelUrl = route('bookings.payment.cancel', ['booking' => $booking->id]);

        try {
            // Get access token
            $accessToken = $this->getAccessToken();

            // Create order
            $response = Http::withToken($accessToken)
                ->post("{$this->baseUrl}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $booking->id,
                            'description' => $booking->service->name,
                            'amount' => [
                                'currency_code' => $booking->currency,
                                'value' => number_format($booking->amount_cents / 100, 2, '.', ''),
                            ],
                        ],
                    ],
                    'application_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'brand_name' => config('app.name'),
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('[PayPalGateway] Payment creation failed', [
                    'booking_id' => $booking->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('PayPal payment creation failed: ' . $response->body());
            }

            $data = $response->json();

            // Get approval link
            $approvalLink = collect($data['links'])->firstWhere('rel', 'approve')['href'] ?? '';

            return new PaymentIntent(
                id: $data['id'],
                redirectUrl: $approvalLink,
                amountCents: $booking->amount_cents,
                currency: $booking->currency,
                status: 'pending',
                metadata: [
                    'order_id' => $data['id'],
                    'status' => $data['status'],
                ],
            );

        } catch (\Exception $e) {
            Log::error('[PayPalGateway] Exception creating payment', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function confirmPayment(string $paymentId): PaymentResult
    {
        $this->ensureConfigured();

        try {
            $accessToken = $this->getAccessToken();

            // Capture the order
            $response = Http::withToken($accessToken)
                ->post("{$this->baseUrl}/v2/checkout/orders/{$paymentId}/capture");

            if (!$response->successful()) {
                Log::error('[PayPalGateway] Payment confirmation failed', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('PayPal payment confirmation failed: ' . $response->body());
            }

            $data = $response->json();

            $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? null;

            if (!$capture) {
                throw new \Exception('PayPal capture data not found');
            }

            // Map PayPal status to our standard status
            $status = $this->mapStatus($capture['status']);

            return new PaymentResult(
                id: $paymentId,
                status: $status,
                amountCents: (int) (floatval($capture['amount']['value']) * 100),
                currency: $capture['amount']['currency_code'],
                transactionId: $capture['id'],
                authorizationCode: $capture['id'],
                metadata: [
                    'payer_email' => $data['payer']['email_address'] ?? null,
                    'payer_id' => $data['payer']['payer_id'] ?? null,
                    'capture_id' => $capture['id'],
                ],
            );

        } catch (\Exception $e) {
            Log::error('[PayPalGateway] Exception confirming payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function refund(string $paymentId, int $amountCents): RefundResult
    {
        $this->ensureConfigured();

        try {
            $accessToken = $this->getAccessToken();

            // Get capture ID from order
            $orderResponse = Http::withToken($accessToken)
                ->get("{$this->baseUrl}/v2/checkout/orders/{$paymentId}");

            $orderData = $orderResponse->json();
            $captureId = $orderData['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

            if (!$captureId) {
                throw new \Exception('PayPal capture ID not found');
            }

            // Create refund
            $response = Http::withToken($accessToken)
                ->post("{$this->baseUrl}/v2/payments/captures/{$captureId}/refund", [
                    'amount' => [
                        'value' => number_format($amountCents / 100, 2, '.', ''),
                        'currency_code' => $orderData['purchase_units'][0]['amount']['currency_code'],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('[PayPalGateway] Refund failed', [
                    'payment_id' => $paymentId,
                    'capture_id' => $captureId,
                    'amount' => $amountCents,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('PayPal refund failed: ' . $response->body());
            }

            $data = $response->json();

            return new RefundResult(
                id: $paymentId,
                status: $data['status'] === 'COMPLETED' ? 'succeeded' : 'pending',
                amountCents: $amountCents,
                currency: $data['amount']['currency_code'],
                refundId: $data['id'],
                metadata: [
                    'capture_id' => $captureId,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[PayPalGateway] Exception processing refund', [
                'payment_id' => $paymentId,
                'amount' => $amountCents,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'paypal';
    }

    /**
     * {@inheritDoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->secret);
    }

    /**
     * Get PayPal access token
     *
     * @return string
     * @throws \Exception
     */
    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get PayPal access token');
        }

        return $response->json()['access_token'];
    }

    /**
     * Ensure gateway is configured
     *
     * @throws \Exception
     */
    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayPal gateway is not configured. Check PAYPAL_CLIENT_ID and PAYPAL_SECRET.');
        }
    }

    /**
     * Map PayPal status to standard status
     *
     * @param string $paypalStatus
     * @return string
     */
    private function mapStatus(string $paypalStatus): string
    {
        return match ($paypalStatus) {
            'COMPLETED' => 'succeeded',
            'DECLINED', 'FAILED' => 'failed',
            'PENDING', 'PARTIALLY_REFUNDED' => 'pending',
            'REFUNDED' => 'refunded',
            default => 'pending',
        };
    }
}

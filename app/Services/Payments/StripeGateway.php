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
 * Stripe Gateway (International)
 *
 * Integration with Stripe Payment API.
 * International payment gateway supporting multiple currencies.
 *
 * @see https://stripe.com/docs/api
 */
class StripeGateway implements PaymentGatewayInterface
{
    private string $publicKey;
    private string $secretKey;
    private string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->publicKey = config('services.payment.stripe.key', '');
        $this->secretKey = config('services.payment.stripe.secret', '');
    }

    /**
     * {@inheritDoc}
     */
    public function createPayment(Booking $booking): PaymentIntent
    {
        $this->ensureConfigured();

        $successUrl = route('bookings.payment.success', ['booking' => $booking->id]);
        $cancelUrl = route('bookings.payment.cancel', ['booking' => $booking->id]);

        try {
            // Create Stripe Checkout Session
            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->post("{$this->baseUrl}/checkout/sessions", [
                    'payment_method_types' => ['card'],
                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => strtolower($booking->currency),
                                'product_data' => [
                                    'name' => $booking->service->name,
                                    'description' => "Reserva para {$booking->scheduled_at->format('Y-m-d H:i')}",
                                ],
                                'unit_amount' => $booking->amount_cents,
                            ],
                            'quantity' => 1,
                        ],
                    ],
                    'mode' => 'payment',
                    'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $cancelUrl,
                    'client_reference_id' => $booking->id,
                    'customer_email' => $booking->client_email,
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'service_id' => $booking->service_id,
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('[StripeGateway] Payment creation failed', [
                    'booking_id' => $booking->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('Stripe payment creation failed: ' . $response->body());
            }

            $data = $response->json();

            return new PaymentIntent(
                id: $data['id'],
                redirectUrl: $data['url'],
                amountCents: $booking->amount_cents,
                currency: $booking->currency,
                status: 'pending',
                metadata: [
                    'session_id' => $data['id'],
                    'payment_intent' => $data['payment_intent'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[StripeGateway] Exception creating payment', [
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
            // Get Checkout Session
            $response = Http::withBasicAuth($this->secretKey, '')
                ->get("{$this->baseUrl}/checkout/sessions/{$paymentId}");

            if (!$response->successful()) {
                Log::error('[StripeGateway] Payment confirmation failed', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('Stripe payment confirmation failed: ' . $response->body());
            }

            $session = $response->json();

            // Get Payment Intent details
            $paymentIntentId = $session['payment_intent'];
            $piResponse = Http::withBasicAuth($this->secretKey, '')
                ->get("{$this->baseUrl}/payment_intents/{$paymentIntentId}");

            $paymentIntent = $piResponse->json();

            // Map Stripe status to our standard status
            $status = $this->mapStatus($paymentIntent['status']);

            return new PaymentResult(
                id: $paymentId,
                status: $status,
                amountCents: $paymentIntent['amount'],
                currency: strtoupper($paymentIntent['currency']),
                transactionId: $paymentIntentId,
                authorizationCode: $paymentIntent['charges']['data'][0]['id'] ?? null,
                metadata: [
                    'payment_method' => $paymentIntent['payment_method'] ?? null,
                    'receipt_url' => $paymentIntent['charges']['data'][0]['receipt_url'] ?? null,
                    'client_reference_id' => $session['client_reference_id'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[StripeGateway] Exception confirming payment', [
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
            // First get the payment intent from the session
            $session = Http::withBasicAuth($this->secretKey, '')
                ->get("{$this->baseUrl}/checkout/sessions/{$paymentId}")
                ->json();

            $paymentIntentId = $session['payment_intent'];

            // Create refund
            $response = Http::withBasicAuth($this->secretKey, '')
                ->asForm()
                ->post("{$this->baseUrl}/refunds", [
                    'payment_intent' => $paymentIntentId,
                    'amount' => $amountCents,
                ]);

            if (!$response->successful()) {
                Log::error('[StripeGateway] Refund failed', [
                    'payment_id' => $paymentId,
                    'amount' => $amountCents,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('Stripe refund failed: ' . $response->body());
            }

            $data = $response->json();

            return new RefundResult(
                id: $paymentId,
                status: $data['status'] === 'succeeded' ? 'succeeded' : 'pending',
                amountCents: $data['amount'],
                currency: strtoupper($data['currency']),
                refundId: $data['id'],
                metadata: [
                    'reason' => $data['reason'] ?? null,
                    'receipt_number' => $data['receipt_number'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[StripeGateway] Exception processing refund', [
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
        return 'stripe';
    }

    /**
     * {@inheritDoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->publicKey) && !empty($this->secretKey);
    }

    /**
     * Ensure gateway is configured
     *
     * @throws \Exception
     */
    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe gateway is not configured. Check STRIPE_KEY and STRIPE_SECRET.');
        }
    }

    /**
     * Map Stripe status to standard status
     *
     * @param string $stripeStatus
     * @return string
     */
    private function mapStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded' => 'succeeded',
            'canceled', 'failed' => 'failed',
            'requires_payment_method', 'requires_confirmation', 'requires_action', 'processing' => 'pending',
            default => 'pending',
        };
    }
}

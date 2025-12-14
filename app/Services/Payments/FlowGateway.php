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
 * Flow Gateway (Chile alternative)
 *
 * Integration with Flow.cl payment API.
 * Alternative Chilean payment gateway.
 *
 * @see https://www.flow.cl/docs/api.html
 */
class FlowGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $secretKey;
    private string $baseUrl = 'https://www.flow.cl/api';

    public function __construct()
    {
        $this->apiKey = config('services.payment.flow.api_key', '');
        $this->secretKey = config('services.payment.flow.secret_key', '');
    }

    /**
     * {@inheritDoc}
     */
    public function createPayment(Booking $booking): PaymentIntent
    {
        $this->ensureConfigured();

        $urlReturn = route('bookings.payment.callback', ['booking' => $booking->id]);
        $urlConfirmation = route('webhooks.flow');

        $params = [
            'apiKey' => $this->apiKey,
            'commerceOrder' => $booking->id,
            'subject' => $booking->service->name,
            'currency' => $booking->currency,
            'amount' => $booking->amount_cents / 100,
            'email' => $booking->client_email,
            'urlConfirmation' => $urlConfirmation,
            'urlReturn' => $urlReturn,
        ];

        // Sign the request
        $params['s'] = $this->sign($params);

        try {
            $response = Http::asForm()
                ->post("{$this->baseUrl}/payment/create", $params);

            if (!$response->successful()) {
                Log::error('[FlowGateway] Payment creation failed', [
                    'booking_id' => $booking->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('Flow payment creation failed: ' . $response->body());
            }

            $data = $response->json();

            return new PaymentIntent(
                id: $data['token'],
                redirectUrl: $data['url'] . '?token=' . $data['token'],
                amountCents: $booking->amount_cents,
                currency: $booking->currency,
                status: 'pending',
                metadata: [
                    'commerce_order' => $booking->id,
                    'flow_order' => $data['flowOrder'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[FlowGateway] Exception creating payment', [
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

        $params = [
            'apiKey' => $this->apiKey,
            'token' => $paymentId,
        ];

        $params['s'] = $this->sign($params);

        try {
            $response = Http::asForm()
                ->get("{$this->baseUrl}/payment/getStatus", $params);

            if (!$response->successful()) {
                Log::error('[FlowGateway] Payment confirmation failed', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('Flow payment confirmation failed: ' . $response->body());
            }

            $data = $response->json();

            // Map Flow status to our standard status
            $status = $this->mapStatus((int) $data['status']);

            return new PaymentResult(
                id: $paymentId,
                status: $status,
                amountCents: (int) ($data['amount'] * 100),
                currency: $data['currency'],
                transactionId: (string) $data['flowOrder'],
                authorizationCode: $data['paymentData']['authorizationCode'] ?? null,
                metadata: [
                    'payment_type' => $data['paymentData']['paymentType'] ?? null,
                    'media' => $data['paymentData']['media'] ?? null,
                    'commerce_order' => $data['commerceOrder'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[FlowGateway] Exception confirming payment', [
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

        $params = [
            'apiKey' => $this->apiKey,
            'token' => $paymentId,
        ];

        $params['s'] = $this->sign($params);

        try {
            $response = Http::asForm()
                ->post("{$this->baseUrl}/payment/refund", $params);

            if (!$response->successful()) {
                Log::error('[FlowGateway] Refund failed', [
                    'payment_id' => $paymentId,
                    'amount' => $amountCents,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('Flow refund failed: ' . $response->body());
            }

            $data = $response->json();

            return new RefundResult(
                id: $paymentId,
                status: isset($data['refundOrder']) ? 'succeeded' : 'failed',
                amountCents: $amountCents,
                currency: 'CLP',
                refundId: $data['refundOrder'] ?? null,
                metadata: [
                    'date' => $data['date'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[FlowGateway] Exception processing refund', [
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
        return 'flow';
    }

    /**
     * {@inheritDoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->secretKey);
    }

    /**
     * Ensure gateway is configured
     *
     * @throws \Exception
     */
    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Flow gateway is not configured. Check FLOW_API_KEY and FLOW_SECRET_KEY.');
        }
    }

    /**
     * Sign request parameters
     *
     * @param array $params
     * @return string
     */
    private function sign(array $params): string
    {
        // Sort params alphabetically
        ksort($params);

        // Concatenate values
        $toSign = '';
        foreach ($params as $key => $value) {
            if ($key !== 's') {
                $toSign .= $value;
            }
        }

        // Sign with secret key
        return hash_hmac('sha256', $toSign, $this->secretKey);
    }

    /**
     * Map Flow status to standard status
     *
     * @param int $flowStatus
     * @return string
     */
    private function mapStatus(int $flowStatus): string
    {
        return match ($flowStatus) {
            1 => 'pending', // Pending
            2 => 'succeeded', // Paid
            3 => 'failed', // Rejected
            4 => 'refunded', // Annulled
            default => 'pending',
        };
    }
}

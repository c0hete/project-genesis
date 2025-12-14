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
 * MercadoPago Gateway (LATAM)
 *
 * Integration with MercadoPago API.
 * Supports multiple LATAM countries.
 *
 * @see https://www.mercadopago.com/developers
 */
class MercadoPagoGateway implements PaymentGatewayInterface
{
    private string $publicKey;
    private string $accessToken;
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->publicKey = config('services.payment.mercadopago.public_key', '');
        $this->accessToken = config('services.payment.mercadopago.access_token', '');
    }

    /**
     * {@inheritDoc}
     */
    public function createPayment(Booking $booking): PaymentIntent
    {
        $this->ensureConfigured();

        $successUrl = route('bookings.payment.success', ['booking' => $booking->id]);
        $failureUrl = route('bookings.payment.failure', ['booking' => $booking->id]);
        $pendingUrl = route('bookings.payment.pending', ['booking' => $booking->id]);

        try {
            $response = Http::withToken($this->accessToken)
                ->post("{$this->baseUrl}/checkout/preferences", [
                    'items' => [
                        [
                            'id' => $booking->service_id,
                            'title' => $booking->service->name,
                            'description' => "Reserva para {$booking->scheduled_at->format('Y-m-d H:i')}",
                            'quantity' => 1,
                            'unit_price' => $booking->amount_cents / 100,
                            'currency_id' => $booking->currency,
                        ],
                    ],
                    'back_urls' => [
                        'success' => $successUrl,
                        'failure' => $failureUrl,
                        'pending' => $pendingUrl,
                    ],
                    'auto_return' => 'approved',
                    'external_reference' => $booking->id,
                    'statement_descriptor' => config('app.name'),
                    'notification_url' => route('webhooks.mercadopago'),
                ]);

            if (!$response->successful()) {
                Log::error('[MercadoPagoGateway] Payment creation failed', [
                    'booking_id' => $booking->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('MercadoPago payment creation failed: ' . $response->body());
            }

            $data = $response->json();

            return new PaymentIntent(
                id: $data['id'],
                redirectUrl: $data['init_point'],
                amountCents: $booking->amount_cents,
                currency: $booking->currency,
                status: 'pending',
                metadata: [
                    'preference_id' => $data['id'],
                    'external_reference' => $booking->id,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[MercadoPagoGateway] Exception creating payment', [
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
            $response = Http::withToken($this->accessToken)
                ->get("{$this->baseUrl}/v1/payments/{$paymentId}");

            if (!$response->successful()) {
                Log::error('[MercadoPagoGateway] Payment confirmation failed', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('MercadoPago payment confirmation failed: ' . $response->body());
            }

            $data = $response->json();

            // Map MercadoPago status to our standard status
            $status = $this->mapStatus($data['status']);

            return new PaymentResult(
                id: $paymentId,
                status: $status,
                amountCents: (int) ($data['transaction_amount'] * 100),
                currency: $data['currency_id'],
                transactionId: (string) $data['id'],
                authorizationCode: $data['authorization_code'] ?? null,
                metadata: [
                    'status_detail' => $data['status_detail'] ?? null,
                    'payment_method_id' => $data['payment_method_id'] ?? null,
                    'payment_type_id' => $data['payment_type_id'] ?? null,
                    'external_reference' => $data['external_reference'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[MercadoPagoGateway] Exception confirming payment', [
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
            $response = Http::withToken($this->accessToken)
                ->post("{$this->baseUrl}/v1/payments/{$paymentId}/refunds", [
                    'amount' => $amountCents / 100,
                ]);

            if (!$response->successful()) {
                Log::error('[MercadoPagoGateway] Refund failed', [
                    'payment_id' => $paymentId,
                    'amount' => $amountCents,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new \Exception('MercadoPago refund failed: ' . $response->body());
            }

            $data = $response->json();

            return new RefundResult(
                id: $paymentId,
                status: $data['status'] === 'approved' ? 'succeeded' : 'pending',
                amountCents: $amountCents,
                currency: $data['currency_id'] ?? 'USD',
                refundId: (string) $data['id'],
                metadata: [
                    'refund_mode' => $data['refund_mode'] ?? null,
                    'status_detail' => $data['status_detail'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[MercadoPagoGateway] Exception processing refund', [
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
        return 'mercadopago';
    }

    /**
     * {@inheritDoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->publicKey) && !empty($this->accessToken);
    }

    /**
     * Ensure gateway is configured
     *
     * @throws \Exception
     */
    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('MercadoPago gateway is not configured. Check MERCADOPAGO_PUBLIC_KEY and MERCADOPAGO_ACCESS_TOKEN.');
        }
    }

    /**
     * Map MercadoPago status to standard status
     *
     * @param string $mpStatus
     * @return string
     */
    private function mapStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved' => 'succeeded',
            'rejected', 'cancelled' => 'failed',
            'pending', 'in_process', 'in_mediation', 'authorized' => 'pending',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }
}

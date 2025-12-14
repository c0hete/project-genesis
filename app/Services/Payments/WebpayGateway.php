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
 * Webpay Gateway (Transbank - Chile)
 *
 * Integration with Transbank's Webpay Plus REST API.
 * Primary payment gateway for Chilean instances.
 *
 * @see https://www.transbankdevelopers.cl/documentacion/webpay-plus
 */
class WebpayGateway implements PaymentGatewayInterface
{
    private string $commerceCode;
    private string $apiKey;
    private string $environment;
    private string $baseUrl;

    public function __construct()
    {
        $this->commerceCode = config('services.payment.webpay.commerce_code', '');
        $this->apiKey = config('services.payment.webpay.api_key', '');
        $this->environment = config('services.payment.webpay.environment', 'production');

        // Set base URL based on environment
        $this->baseUrl = $this->environment === 'production'
            ? 'https://webpay3g.transbank.cl'
            : 'https://webpay3gint.transbank.cl';
    }

    /**
     * {@inheritDoc}
     */
    public function createPayment(Booking $booking): PaymentIntent
    {
        $this->ensureConfigured();

        $returnUrl = route('bookings.payment.callback', ['booking' => $booking->id]);

        try {
            $response = Http::withHeaders([
                'Tbk-Api-Key-Id' => $this->commerceCode,
                'Tbk-Api-Key-Secret' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/rswebpaytransaction/api/webpay/v1.2/transactions", [
                'buy_order' => $booking->id,
                'session_id' => session()->getId(),
                'amount' => $booking->amount_cents,
                'return_url' => $returnUrl,
            ]);

            if (!$response->successful()) {
                Log::error('[WebpayGateway] Payment creation failed', [
                    'booking_id' => $booking->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Webpay payment creation failed: ' . $response->body());
            }

            $data = $response->json();

            return new PaymentIntent(
                id: $data['token'],
                redirectUrl: $data['url'] . '?token_ws=' . $data['token'],
                amountCents: $booking->amount_cents,
                currency: $booking->currency,
                status: 'pending',
                metadata: [
                    'buy_order' => $booking->id,
                    'session_id' => session()->getId(),
                ],
            );

        } catch (\Exception $e) {
            Log::error('[WebpayGateway] Exception creating payment', [
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
            $response = Http::withHeaders([
                'Tbk-Api-Key-Id' => $this->commerceCode,
                'Tbk-Api-Key-Secret' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->put("{$this->baseUrl}/rswebpaytransaction/api/webpay/v1.2/transactions/{$paymentId}");

            if (!$response->successful()) {
                Log::error('[WebpayGateway] Payment confirmation failed', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Webpay payment confirmation failed: ' . $response->body());
            }

            $data = $response->json();

            // Map Webpay status to our standard status
            $status = $this->mapStatus($data['status']);

            return new PaymentResult(
                id: $paymentId,
                status: $status,
                amountCents: (int) $data['amount'],
                currency: 'CLP',
                transactionId: $data['transaction_date'] ?? null,
                authorizationCode: $data['authorization_code'] ?? null,
                metadata: [
                    'vci' => $data['vci'] ?? null,
                    'card_number' => $data['card_detail']['card_number'] ?? null,
                    'accounting_date' => $data['accounting_date'] ?? null,
                    'buy_order' => $data['buy_order'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[WebpayGateway] Exception confirming payment', [
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
            $response = Http::withHeaders([
                'Tbk-Api-Key-Id' => $this->commerceCode,
                'Tbk-Api-Key-Secret' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/rswebpaytransaction/api/webpay/v1.2/transactions/{$paymentId}/refunds", [
                'amount' => $amountCents,
            ]);

            if (!$response->successful()) {
                Log::error('[WebpayGateway] Refund failed', [
                    'payment_id' => $paymentId,
                    'amount' => $amountCents,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Webpay refund failed: ' . $response->body());
            }

            $data = $response->json();

            return new RefundResult(
                id: $paymentId,
                status: $data['type'] === 'REVERSED' ? 'succeeded' : 'failed',
                amountCents: $amountCents,
                currency: 'CLP',
                refundId: $data['authorization_code'] ?? null,
                metadata: [
                    'type' => $data['type'] ?? null,
                    'authorization_date' => $data['authorization_date'] ?? null,
                    'nullified_amount' => $data['nullified_amount'] ?? null,
                ],
            );

        } catch (\Exception $e) {
            Log::error('[WebpayGateway] Exception processing refund', [
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
        return 'webpay';
    }

    /**
     * {@inheritDoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->commerceCode) && !empty($this->apiKey);
    }

    /**
     * Ensure gateway is configured
     *
     * @throws \Exception
     */
    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Webpay gateway is not configured. Check WEBPAY_COMMERCE_CODE and WEBPAY_API_KEY.');
        }
    }

    /**
     * Map Webpay status to standard status
     *
     * @param string $webpayStatus
     * @return string
     */
    private function mapStatus(string $webpayStatus): string
    {
        return match ($webpayStatus) {
            'AUTHORIZED' => 'succeeded',
            'FAILED' => 'failed',
            default => 'pending',
        };
    }
}

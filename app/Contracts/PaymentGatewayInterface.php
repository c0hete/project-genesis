<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\PaymentIntent;
use App\DTOs\PaymentResult;
use App\DTOs\RefundResult;
use App\Models\Booking;

/**
 * Payment Gateway Interface
 *
 * Abstract interface for all payment gateways.
 * Allows Genesis instances to support multiple payment providers.
 *
 * Supported gateways:
 * - Webpay (Transbank, Chile)
 * - MercadoPago (LATAM)
 * - Flow.cl (Chile alternative)
 * - Stripe (International)
 * - PayPal (Backup)
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment intent for a booking
     *
     * @param Booking $booking The booking to create payment for
     * @return PaymentIntent Payment intent with redirect URL
     * @throws \Exception If payment creation fails
     */
    public function createPayment(Booking $booking): PaymentIntent;

    /**
     * Confirm payment after redirect/callback
     *
     * @param string $paymentId Gateway-specific payment ID
     * @return PaymentResult Payment result with status
     * @throws \Exception If confirmation fails
     */
    public function confirmPayment(string $paymentId): PaymentResult;

    /**
     * Refund a payment
     *
     * @param string $paymentId Gateway-specific payment ID
     * @param int $amountCents Amount to refund in cents
     * @return RefundResult Refund result with status
     * @throws \Exception If refund fails
     */
    public function refund(string $paymentId, int $amountCents): RefundResult;

    /**
     * Get gateway name
     *
     * @return string Gateway identifier (webpay, stripe, etc.)
     */
    public function getName(): string;

    /**
     * Check if gateway is properly configured
     *
     * @return bool True if ready to use
     */
    public function isConfigured(): bool;
}

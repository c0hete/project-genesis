<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payments\FlowGateway;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\StripeGateway;
use App\Services\Payments\WebpayGateway;
use Illuminate\Support\Facades\Log;

/**
 * Payment Gateway Factory
 *
 * Creates payment gateway instances based on configuration.
 * Allows per-instance gateway selection via PAYMENT_GATEWAY env var.
 *
 * Supported gateways:
 * - webpay (Transbank, Chile)
 * - mercadopago (LATAM)
 * - flow (Chile alternative)
 * - stripe (International)
 * - paypal (Backup)
 */
class PaymentGatewayFactory
{
    /**
     * Create gateway instance
     *
     * @param string|null $gateway Gateway name (defaults to config)
     * @return PaymentGatewayInterface
     * @throws \InvalidArgumentException If gateway not supported
     */
    public function make(?string $gateway = null): PaymentGatewayInterface
    {
        $gatewayName = $gateway ?? config('services.payment.gateway', 'stripe');

        Log::info('[PaymentGatewayFactory] Creating gateway', [
            'gateway' => $gatewayName,
        ]);

        $instance = match ($gatewayName) {
            'webpay' => new WebpayGateway(),
            'mercadopago' => new MercadoPagoGateway(),
            'flow' => new FlowGateway(),
            'stripe' => new StripeGateway(),
            'paypal' => new PayPalGateway(),
            default => throw new \InvalidArgumentException("Unsupported payment gateway: {$gatewayName}"),
        };

        // Validate configuration
        if (!$instance->isConfigured()) {
            Log::warning('[PaymentGatewayFactory] Gateway not properly configured', [
                'gateway' => $gatewayName,
            ]);
        }

        return $instance;
    }

    /**
     * Get list of supported gateways
     *
     * @return array<string>
     */
    public function getSupportedGateways(): array
    {
        return [
            'webpay',
            'mercadopago',
            'flow',
            'stripe',
            'paypal',
        ];
    }

    /**
     * Get list of configured gateways
     *
     * @return array<string, bool> Gateway name => is configured
     */
    public function getConfiguredGateways(): array
    {
        $gateways = [];

        foreach ($this->getSupportedGateways() as $gatewayName) {
            try {
                $gateway = $this->make($gatewayName);
                $gateways[$gatewayName] = $gateway->isConfigured();
            } catch (\Exception $e) {
                $gateways[$gatewayName] = false;
            }
        }

        return $gateways;
    }

    /**
     * Check if current gateway is configured
     *
     * @return bool
     */
    public function isCurrentGatewayConfigured(): bool
    {
        try {
            $gateway = $this->make();
            return $gateway->isConfigured();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get current gateway name
     *
     * @return string
     */
    public function getCurrentGateway(): string
    {
        return config('services.payment.gateway', 'stripe');
    }
}

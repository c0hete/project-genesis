<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Payment Intent DTO
 *
 * Immutable data structure for payment creation responses.
 */
readonly class PaymentIntent
{
    public function __construct(
        public string $id,
        public string $redirectUrl,
        public int $amountCents,
        public string $currency,
        public string $status,
        public ?array $metadata = null,
    ) {
    }

    /**
     * Create from array
     */
    public static function from(array $data): self
    {
        return new self(
            id: $data['id'],
            redirectUrl: $data['redirect_url'],
            amountCents: $data['amount_cents'],
            currency: $data['currency'],
            status: $data['status'],
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'redirect_url' => $this->redirectUrl,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}

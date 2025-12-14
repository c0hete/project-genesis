<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Payment Result DTO
 *
 * Immutable data structure for payment confirmation responses.
 */
readonly class PaymentResult
{
    public function __construct(
        public string $id,
        public string $status,
        public int $amountCents,
        public string $currency,
        public ?string $transactionId = null,
        public ?string $authorizationCode = null,
        public ?array $metadata = null,
    ) {
    }

    /**
     * Check if payment was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded' || $this->status === 'paid';
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed' || $this->status === 'declined';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'processing';
    }

    /**
     * Create from array
     */
    public static function from(array $data): self
    {
        return new self(
            id: $data['id'],
            status: $data['status'],
            amountCents: $data['amount_cents'],
            currency: $data['currency'],
            transactionId: $data['transaction_id'] ?? null,
            authorizationCode: $data['authorization_code'] ?? null,
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
            'status' => $this->status,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'transaction_id' => $this->transactionId,
            'authorization_code' => $this->authorizationCode,
            'metadata' => $this->metadata,
        ];
    }
}

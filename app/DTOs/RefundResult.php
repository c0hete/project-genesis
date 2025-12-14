<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Refund Result DTO
 *
 * Immutable data structure for refund responses.
 */
readonly class RefundResult
{
    public function __construct(
        public string $id,
        public string $status,
        public int $amountCents,
        public string $currency,
        public ?string $refundId = null,
        public ?array $metadata = null,
    ) {
    }

    /**
     * Check if refund was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded' || $this->status === 'refunded';
    }

    /**
     * Check if refund failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if refund is pending
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
            refundId: $data['refund_id'] ?? null,
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
            'refund_id' => $this->refundId,
            'metadata' => $this->metadata,
        ];
    }
}

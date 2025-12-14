<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Booking Status Enum
 *
 * 8-state booking lifecycle based on corporate standards.
 * Each state transition triggers a Hub event.
 *
 * @see C:\Users\JoseA\Projects\knowledge-base\projects\project-genesis\SUPERVISOR_INTEGRATION.md
 */
enum BookingStatus: string
{
    /**
     * Initial state - Booking created but not confirmed
     */
    case CREATED = 'created';

    /**
     * Payment successful, booking confirmed
     */
    case CONFIRMED = 'confirmed';

    /**
     * Reminder sent (typically 24h before)
     */
    case REMINDED = 'reminded';

    /**
     * Service started (check-in)
     */
    case STARTED = 'started';

    /**
     * Service completed (check-out)
     */
    case COMPLETED = 'completed';

    /**
     * Client didn't show up
     */
    case NO_SHOW = 'no_show';

    /**
     * Cancelled by client or staff
     */
    case CANCELLED = 'cancelled';

    /**
     * Rescheduled to different date/time
     */
    case RESCHEDULED = 'rescheduled';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::CONFIRMED => 'Confirmed',
            self::REMINDED => 'Reminded',
            self::STARTED => 'Started',
            self::COMPLETED => 'Completed',
            self::NO_SHOW => 'No Show',
            self::CANCELLED => 'Cancelled',
            self::RESCHEDULED => 'Rescheduled',
        };
    }

    /**
     * Get color for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'gray',
            self::CONFIRMED => 'blue',
            self::REMINDED => 'purple',
            self::STARTED => 'yellow',
            self::COMPLETED => 'green',
            self::NO_SHOW => 'red',
            self::CANCELLED => 'red',
            self::RESCHEDULED => 'orange',
        };
    }

    /**
     * Check if booking can be cancelled
     */
    public function isCancellable(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::CONFIRMED,
            self::REMINDED,
        ]);
    }

    /**
     * Check if booking can be rescheduled
     */
    public function isReschedulable(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::CONFIRMED,
            self::REMINDED,
        ]);
    }

    /**
     * Check if booking is active (not terminal state)
     */
    public function isActive(): bool
    {
        return !in_array($this, [
            self::COMPLETED,
            self::NO_SHOW,
            self::CANCELLED,
        ]);
    }

    /**
     * Check if booking is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Valid state transitions
     *
     * @return array<BookingStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::CREATED => [
                self::CONFIRMED,
                self::CANCELLED,
                self::RESCHEDULED,
            ],
            self::CONFIRMED => [
                self::REMINDED,
                self::STARTED,
                self::NO_SHOW,
                self::CANCELLED,
                self::RESCHEDULED,
            ],
            self::REMINDED => [
                self::STARTED,
                self::NO_SHOW,
                self::CANCELLED,
                self::RESCHEDULED,
            ],
            self::STARTED => [
                self::COMPLETED,
            ],
            self::COMPLETED => [],
            self::NO_SHOW => [],
            self::CANCELLED => [],
            self::RESCHEDULED => [
                self::CONFIRMED,
            ],
        };
    }

    /**
     * Check if transition is allowed
     */
    public function canTransitionTo(BookingStatus $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions());
    }
}

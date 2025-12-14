<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Services\HubEventReporter;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * Booking Model
 *
 * Inspired by Calendly, Square Appointments, Salesforce Service Cloud
 *
 * Features:
 * - 8-state lifecycle with automatic Hub event reporting
 * - Payment tracking (multiple gateways)
 * - Rescheduling and cancellation support
 * - Staff assignment
 * - Automated reminders
 */
class Booking extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'service_id',
        'user_id',
        'assigned_to',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'duration_minutes',
        'actual_duration_minutes',
        'amount_cents',
        'currency',
        'deposit_cents',
        'is_paid',
        'payment_status',
        'payment_method',
        'payment_intent_id',
        'client_name',
        'client_email',
        'client_phone',
        'client_notes',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'rescheduled_from',
        'rescheduled_at',
        'reminder_sent',
        'reminder_sent_at',
        'confirmation_sent',
        'staff_notes',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rescheduled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'is_paid' => 'boolean',
        'reminder_sent' => 'boolean',
        'confirmation_sent' => 'boolean',
    ];

    /**
     * Boot model events
     */
    protected static function booted(): void
    {
        // Report to Hub when booking status changes
        static::updated(function (Booking $booking) {
            if ($booking->wasChanged('status')) {
                $booking->reportStatusChange(
                    $booking->getOriginal('status'),
                    $booking->status
                );
            }
        });

        // Report creation
        static::created(function (Booking $booking) {
            $booking->reportToHub('booking.created', [
                'booking_id' => $booking->id,
                'service_name' => $booking->service->name,
                'duration_minutes' => $booking->duration_minutes,
                'scheduled_at' => $booking->scheduled_at->toIso8601String(),
                'amount_cents' => $booking->amount_cents,
                'currency' => $booking->currency,
            ]);
        });
    }

    // Relations

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rescheduledFromBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rescheduled_from');
    }

    // State transitions (based on BookingStatus allowed transitions)

    public function confirm(): bool
    {
        return $this->transitionTo(BookingStatus::CONFIRMED);
    }

    public function markReminded(): bool
    {
        $this->reminder_sent = true;
        $this->reminder_sent_at = now();
        $this->save();

        return $this->transitionTo(BookingStatus::REMINDED);
    }

    public function start(): bool
    {
        $this->started_at = now();
        $this->save();

        return $this->transitionTo(BookingStatus::STARTED);
    }

    public function complete(?int $actualDurationMinutes = null): bool
    {
        $this->completed_at = now();

        if ($actualDurationMinutes) {
            $this->actual_duration_minutes = $actualDurationMinutes;
        } elseif ($this->started_at) {
            $this->actual_duration_minutes = (int) $this->started_at->diffInMinutes(now());
        }

        $this->save();

        return $this->transitionTo(BookingStatus::COMPLETED);
    }

    public function markNoShow(): bool
    {
        return $this->transitionTo(BookingStatus::NO_SHOW);
    }

    public function cancel(string $reason, ?User $cancelledBy = null): bool
    {
        $this->cancellation_reason = $reason;
        $this->cancelled_by = $cancelledBy?->id;
        $this->cancelled_at = now();
        $this->save();

        return $this->transitionTo(BookingStatus::CANCELLED);
    }

    public function reschedule(\DateTime $newDateTime): ?Booking
    {
        // Create new booking
        $newBooking = $this->replicate();
        $newBooking->scheduled_at = $newDateTime;
        $newBooking->status = BookingStatus::CREATED;
        $newBooking->rescheduled_from = $this->id;
        $newBooking->rescheduled_at = now();
        $newBooking->save();

        // Mark current as rescheduled
        $this->transitionTo(BookingStatus::RESCHEDULED);

        return $newBooking;
    }

    /**
     * Internal state transition with validation
     */
    protected function transitionTo(BookingStatus $newStatus): bool
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            Log::warning('[Booking] Invalid state transition', [
                'booking_id' => $this->id,
                'from' => $this->status->value,
                'to' => $newStatus->value,
            ]);
            return false;
        }

        $this->status = $newStatus;
        return $this->save();
    }

    /**
     * Report status change to Hub
     */
    protected function reportStatusChange(BookingStatus|string $oldStatus, BookingStatus $newStatus): void
    {
        $action = match ($newStatus) {
            BookingStatus::CONFIRMED => 'booking.confirmed',
            BookingStatus::REMINDED => 'booking.reminded',
            BookingStatus::STARTED => 'booking.started',
            BookingStatus::COMPLETED => 'booking.completed',
            BookingStatus::NO_SHOW => 'booking.no_show',
            BookingStatus::CANCELLED => 'booking.cancelled',
            BookingStatus::RESCHEDULED => 'booking.rescheduled',
            default => null,
        };

        if (!$action) {
            return;
        }

        $payload = [
            'booking_id' => $this->id,
            'service_name' => $this->service->name,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
        ];

        // Add state-specific data
        if ($newStatus === BookingStatus::COMPLETED) {
            $payload['completed_at'] = $this->completed_at?->toIso8601String();
            $payload['actual_duration_minutes'] = $this->actual_duration_minutes;
        }

        if ($newStatus === BookingStatus::CANCELLED) {
            $payload['cancelled_by'] = $this->cancelled_by ? 'staff' : 'client';
            $payload['reason'] = $this->cancellation_reason;
        }

        $this->reportToHub($action, $payload);
    }

    /**
     * Report event to Hub Personal
     */
    protected function reportToHub(string $action, array $data): void
    {
        try {
            app(HubEventReporter::class)->bookingEvent($action, $data);
        } catch (\Exception $e) {
            // Never crash booking flow if Hub reporting fails
            Log::error('[Booking] Hub reporting failed', [
                'action' => $action,
                'booking_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Business logic helpers (Salesforce pattern)

    public function canBeCancelled(): bool
    {
        return $this->status->isCancellable();
    }

    public function canBeRescheduled(): bool
    {
        return $this->status->isReschedulable();
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture() && $this->status->isActive();
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    public function isToday(): bool
    {
        return $this->scheduled_at->isToday();
    }

    public function requiresReminder(): bool
    {
        return !$this->reminder_sent
            && $this->status === BookingStatus::CONFIRMED
            && $this->scheduled_at->subHours(24)->isPast()
            && $this->scheduled_at->isFuture();
    }

    // Accessors (for UI display - Stripe pattern)

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_cents / 100, 2, '.', '');
    }

    public function getFormattedDepositAttribute(): ?string
    {
        return $this->deposit_cents
            ? number_format($this->deposit_cents / 100, 2, '.', '')
            : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    // Scopes (for common queries)

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
            ->whereIn('status', [
                BookingStatus::CREATED->value,
                BookingStatus::CONFIRMED->value,
                BookingStatus::REMINDED->value,
            ]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }

    public function scopePending($query)
    {
        return $query->where('status', BookingStatus::CREATED->value);
    }

    public function scopeConfirmed($query)
    {
        return $query->whereIn('status', [
            BookingStatus::CONFIRMED->value,
            BookingStatus::REMINDED->value,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', BookingStatus::COMPLETED->value);
    }

    public function scopeRequiringReminder($query)
    {
        return $query->where('status', BookingStatus::CONFIRMED->value)
            ->where('reminder_sent', false)
            ->where('scheduled_at', '<=', now()->addHours(24))
            ->where('scheduled_at', '>=', now());
    }
}

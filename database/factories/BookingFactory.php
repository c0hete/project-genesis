<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledAt = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $durationMinutes = $this->faker->randomElement([30, 45, 60, 90, 120]);

        return [
            'service_id' => Service::factory(),
            'user_id' => User::factory(),
            'status' => BookingStatus::CREATED,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $durationMinutes,
            'amount_cents' => $this->faker->numberBetween(2000, 20000),
            'currency' => $this->faker->randomElement(['USD', 'CLP', 'MXN', 'ARS']),
            'client_name' => $this->faker->name(),
            'client_email' => $this->faker->safeEmail(),
            'client_phone' => $this->faker->phoneNumber(),
            'client_notes' => $this->faker->optional(0.3)->sentence(),
            'is_paid' => false,
            'payment_status' => 'pending',
            'reminder_sent' => false,
            'confirmation_sent' => false,
            'source' => $this->faker->randomElement(['web', 'mobile', 'phone', 'walk-in']),
        ];
    }

    /**
     * Indicate that the booking is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CONFIRMED,
            'is_paid' => true,
            'payment_status' => 'paid',
            'confirmation_sent' => true,
        ]);
    }

    /**
     * Indicate that the booking has been reminded.
     */
    public function reminded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::REMINDED,
            'is_paid' => true,
            'payment_status' => 'paid',
            'reminder_sent' => true,
            'reminder_sent_at' => now()->subHours(2),
        ]);
    }

    /**
     * Indicate that the booking has started.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::STARTED,
            'is_paid' => true,
            'payment_status' => 'paid',
            'started_at' => now()->subMinutes(15),
        ]);
    }

    /**
     * Indicate that the booking is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::COMPLETED,
            'is_paid' => true,
            'payment_status' => 'paid',
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'actual_duration_minutes' => $attributes['duration_minutes'] ?? 60,
        ]);
    }

    /**
     * Indicate that the booking was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CANCELLED,
            'cancellation_reason' => $this->faker->randomElement([
                'Client request',
                'Schedule conflict',
                'Personal emergency',
                'Weather',
            ]),
            'cancelled_at' => now()->subHours(1),
        ]);
    }

    /**
     * Indicate that the booking was a no-show.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::NO_SHOW,
            'scheduled_at' => now()->subHours(2),
        ]);
    }

    /**
     * Indicate that the booking was rescheduled.
     */
    public function rescheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::RESCHEDULED,
            'rescheduled_at' => now()->subHours(1),
        ]);
    }

    /**
     * Indicate that the booking is for today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => now()->addHours($this->faker->numberBetween(1, 8)),
        ]);
    }

    /**
     * Indicate that the booking is in the past.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ]);
    }
}

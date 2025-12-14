<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Booking Lifecycle Feature Tests
 *
 * Tests all 8 states of the booking lifecycle:
 * created → confirmed → reminded → started → completed
 * created → cancelled
 * created → rescheduled
 * confirmed → no_show
 */
class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->service = Service::factory()->create([
            'name' => 'Test Service',
            'duration_minutes' => 60,
            'price_cents' => 5000,
            'currency' => 'USD',
        ]);
    }

    public function test_booking_starts_in_created_state(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertEquals(BookingStatus::CREATED, $booking->status);
    }

    public function test_booking_can_transition_to_confirmed(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::CREATED,
        ]);

        $result = $booking->confirm();

        $this->assertTrue($result);
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->fresh()->status);
    }

    public function test_booking_can_transition_to_reminded(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $result = $booking->markReminded();

        $this->assertTrue($result);
        $this->assertEquals(BookingStatus::REMINDED, $booking->fresh()->status);
        $this->assertTrue($booking->fresh()->reminder_sent);
        $this->assertNotNull($booking->fresh()->reminder_sent_at);
    }

    public function test_booking_can_transition_to_started(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::REMINDED,
        ]);

        $result = $booking->start();

        $this->assertTrue($result);
        $this->assertEquals(BookingStatus::STARTED, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->started_at);
    }

    public function test_booking_can_transition_to_completed(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::STARTED,
            'started_at' => now()->subMinutes(60),
        ]);

        $result = $booking->complete();

        $this->assertTrue($result);
        $this->assertEquals(BookingStatus::COMPLETED, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->completed_at);
        $this->assertNotNull($booking->fresh()->actual_duration_minutes);
    }

    public function test_booking_can_be_cancelled(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $result = $booking->cancel('Client request', $this->user);

        $this->assertTrue($result);
        $this->assertEquals(BookingStatus::CANCELLED, $booking->fresh()->status);
        $this->assertEquals('Client request', $booking->fresh()->cancellation_reason);
        $this->assertEquals($this->user->id, $booking->fresh()->cancelled_by);
        $this->assertNotNull($booking->fresh()->cancelled_at);
    }

    public function test_booking_can_be_marked_no_show(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::REMINDED,
        ]);

        $result = $booking->markNoShow();

        $this->assertTrue($result);
        $this->assertEquals(BookingStatus::NO_SHOW, $booking->fresh()->status);
    }

    public function test_booking_can_be_rescheduled(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::CONFIRMED,
            'scheduled_at' => now()->addDays(2),
        ]);

        $newDateTime = now()->addDays(5);
        $newBooking = $booking->reschedule($newDateTime);

        $this->assertNotNull($newBooking);
        $this->assertEquals(BookingStatus::RESCHEDULED, $booking->fresh()->status);
        $this->assertEquals(BookingStatus::CREATED, $newBooking->status);
        $this->assertEquals($newDateTime->format('Y-m-d H:i'), $newBooking->scheduled_at->format('Y-m-d H:i'));
        $this->assertEquals($booking->id, $newBooking->rescheduled_from);
    }

    public function test_invalid_state_transitions_are_rejected(): void
    {
        $booking = Booking::factory()->create([
            'service_id' => $this->service->id,
            'user_id' => $this->user->id,
            'status' => BookingStatus::COMPLETED,
        ]);

        // Completed bookings cannot be confirmed again
        $result = $booking->confirm();

        $this->assertFalse($result);
        $this->assertEquals(BookingStatus::COMPLETED, $booking->fresh()->status);
    }

    public function test_booking_can_check_cancellability(): void
    {
        $created = Booking::factory()->create(['status' => BookingStatus::CREATED]);
        $completed = Booking::factory()->create(['status' => BookingStatus::COMPLETED]);

        $this->assertTrue($created->canBeCancelled());
        $this->assertFalse($completed->canBeCancelled());
    }

    public function test_booking_can_check_reschedulability(): void
    {
        $confirmed = Booking::factory()->create(['status' => BookingStatus::CONFIRMED]);
        $completed = Booking::factory()->create(['status' => BookingStatus::COMPLETED]);

        $this->assertTrue($confirmed->canBeRescheduled());
        $this->assertFalse($completed->canBeRescheduled());
    }

    public function test_booking_scopes_work_correctly(): void
    {
        // Create bookings in different states
        Booking::factory()->create(['status' => BookingStatus::CONFIRMED, 'scheduled_at' => now()->addDay()]);
        Booking::factory()->create(['status' => BookingStatus::COMPLETED, 'scheduled_at' => now()->subDay()]);
        Booking::factory()->create(['status' => BookingStatus::CANCELLED, 'scheduled_at' => now()->addDay()]);

        $upcoming = Booking::upcoming()->count();
        $completed = Booking::completed()->count();

        $this->assertEquals(1, $upcoming);
        $this->assertEquals(1, $completed);
    }
}

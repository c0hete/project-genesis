<?php

use App\DTOs\TimeSlot;
use App\Models\Service;
use App\Services\AvailabilityGenerator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

test('it generates slots for a standard day', function () {
    // Arrange
    $service = Service::factory()->make([
        'duration_minutes' => 60,
    ]);
    
    $generator = new AvailabilityGenerator();
    $date = Carbon::parse('2025-12-15'); // Monday (Mon-Fri 09:00-17:00)

    // Act
    $slots = $generator->getSlotsForDate($service, $date);

    // Assert
    // 09:00-10:00, 10:00-11:00, ..., 16:00-17:00 => 8 slots
    expect($slots)->toHaveCount(8);
    expect($slots->first())->toBeInstanceOf(TimeSlot::class);
    expect($slots->first()->start->format('H:i'))->toBe('09:00');
    expect($slots->first()->end->format('H:i'))->toBe('10:00');
    expect($slots->last()->start->format('H:i'))->toBe('16:00');
    expect($slots->last()->end->format('H:i'))->toBe('17:00');
});

test('it returns empty slots for closed days', function () {
    // Arrange
    $service = Service::factory()->make(['duration_minutes' => 60]);
    $generator = new AvailabilityGenerator();
    $date = Carbon::parse('2025-12-13'); // Saturday

    // Act
    $slots = $generator->getSlotsForDate($service, $date);

    // Assert
    expect($slots)->toBeEmpty();
});

test('it handles dead time correctly', function () {
    // Arrange
    // Service 45 mins. Day 09:00 - 17:00.
    // Slots: 09:00-09:45, 09:45-10:30, 10:30-11:15, 11:15-12:00, ...
    // Let's test a shorter window to be precise or just verify the math.
    // If we have 09:00 to 10:00 available (1 hour) and service is 45 mins.
    // We should have 1 slot: 09:00-09:45.
    // The next potential slot 09:45-10:30 goes beyond 10:00.
    
    // But the requirement says "static business hours (Mon-Fri, 09:00-17:00)".
    // So for 45 mins:
    // 09:00, 09:45, 10:30, 11:15, 12:00, 12:45, 13:30, 14:15, 15:00, 15:45, 16:30 (ends 17:15 - invalid)
    // So last slot is 15:45-16:30.
    // Wait, 16:30-17:15 is invalid.
    // So we expect slots starting at:
    // 9:00, 9:45, 10:30, 11:15, 12:00, 12:45, 13:30, 14:15, 15:00, 15:45.
    // Total 10 slots.
    
    $service = Service::factory()->make(['duration_minutes' => 45]);
    $generator = new AvailabilityGenerator();
    $date = Carbon::parse('2025-12-15'); // Monday

    // Act
    $slots = $generator->getSlotsForDate($service, $date);

    // Assert
    expect($slots)->toHaveCount(10);
    expect($slots->last()->end->format('H:i') <= '17:00')->toBeTrue();
});

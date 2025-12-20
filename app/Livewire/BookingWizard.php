<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Service;
use App\Services\AvailabilityGenerator;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Booking Wizard - Multi-step booking flow (Calendly-style)
 *
 * Step 1: Select date and time
 * Step 2: Enter client details
 * Step 3: Confirmation and payment
 */
class BookingWizard extends Component
{
    // Step control
    public int $currentStep = 1;

    // Service being booked
    public Service $service;

    // Step 1: Date and time selection
    public string $selectedDate = '';
    public string $selectedTime = '';
    public int $currentMonth;
    public int $currentYear;
    public array $availableSlots = [];

    // Step 2: Client details
    public string $clientName = '';
    public string $clientEmail = '';
    public string $clientPhone = '';
    public string $notes = '';

    // Step 3: Booking created
    public ?Booking $booking = null;

    public function mount(Service $service)
    {
        $this->service = $service;
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
    }

    public function selectDate(string $date)
    {
        $this->selectedDate = $date;
        $this->loadSlotsForDate($date);
    }

    public function selectTime(string $time)
    {
        $this->selectedTime = $time;
    }

    public function loadSlotsForDate(string $date)
    {
        $generator = new AvailabilityGenerator();
        $carbonDate = Carbon::parse($date);

        // Get available slots for this service and date
        $slots = $generator->getSlotsForDate($this->service, $carbonDate);

        // Get existing bookings for this date
        $existingBookings = Booking::query()
            ->where('service_id', $this->service->id)
            ->whereDate('scheduled_at', $carbonDate)
            ->whereIn('status', ['created', 'confirmed', 'reminded', 'started'])
            ->get();

        // Filter out occupied slots
        $this->availableSlots = $slots->filter(function ($slot) use ($existingBookings) {
            foreach ($existingBookings as $booking) {
                $bookingStart = $booking->scheduled_at;
                $bookingEnd = $bookingStart->copy()->addMinutes($this->service->duration_minutes);

                // Check if slot overlaps with existing booking
                if (
                    $slot->start->between($bookingStart, $bookingEnd, false) ||
                    $slot->end->between($bookingStart, $bookingEnd, false)
                ) {
                    return false;
                }
            }
            return true;
        })->map(function ($slot) {
            return [
                'time' => $slot->start->format('H:i'),
                'formatted' => $slot->start->format('H:i'),
                'period' => $slot->start->hour < 12 ? 'morning' : 'afternoon',
            ];
        })->values()->toArray();
    }

    public function nextMonth()
    {
        if ($this->currentMonth === 12) {
            $this->currentMonth = 1;
            $this->currentYear++;
        } else {
            $this->currentMonth++;
        }
    }

    public function previousMonth()
    {
        if ($this->currentMonth === 1) {
            $this->currentMonth = 12;
            $this->currentYear--;
        } else {
            $this->currentMonth--;
        }
    }

    public function goToStep2()
    {
        $this->validate([
            'selectedDate' => 'required|date|after:yesterday',
            'selectedTime' => 'required',
        ]);

        $this->currentStep = 2;
    }

    public function goToStep3()
    {
        $this->validate([
            'clientName' => 'required|min:3|max:255',
            'clientEmail' => 'required|email|max:255',
            'clientPhone' => 'nullable|max:20',
            'notes' => 'nullable|max:1000',
        ]);

        // Create or find user by email
        $user = \App\Models\User::firstOrCreate(
            ['email' => $this->clientEmail],
            [
                'name' => $this->clientName,
                'password' => bcrypt(str()->random(16)), // Temporary password
            ]
        );

        // Create booking
        $scheduledAt = Carbon::parse($this->selectedDate . ' ' . $this->selectedTime);

        $this->booking = Booking::create([
            'user_id' => $user->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $scheduledAt,
            'client_name' => $this->clientName,
            'client_email' => $this->clientEmail,
            'client_phone' => $this->clientPhone,
            'notes' => $this->notes,
            'status' => \App\Enums\BookingStatus::CREATED,
        ]);

        $this->currentStep = 3;
    }

    public function backToStep1()
    {
        $this->currentStep = 1;
    }

    public function backToStep2()
    {
        $this->currentStep = 2;
    }

    public function render()
    {
        // Generate calendar days for current month
        $firstDay = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $daysInMonth = $firstDay->daysInMonth;
        $startDayOfWeek = $firstDay->dayOfWeek;

        $calendarDays = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = Carbon::create($this->currentYear, $this->currentMonth, $i);
            $calendarDays[] = [
                'day' => $i,
                'date' => $date->format('Y-m-d'),
                'isWeekend' => $date->isWeekend(),
                'isPast' => $date->isPast() && !$date->isToday(),
                'isToday' => $date->isToday(),
            ];
        }

        return view('livewire.booking-wizard', [
            'calendarDays' => $calendarDays,
            'startDayOfWeek' => $startDayOfWeek,
            'monthName' => Carbon::create($this->currentYear, $this->currentMonth, 1)->monthName,
        ]);
    }
}

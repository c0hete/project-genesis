<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Service;
use Livewire\Component;

/**
 * Booking Landing Page
 *
 * Public landing page showing available services (Calendly-style).
 * No authentication required.
 */
class BookingLanding extends Component
{
    public function render()
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.booking-landing', [
            'services' => $services,
        ]);
    }
}

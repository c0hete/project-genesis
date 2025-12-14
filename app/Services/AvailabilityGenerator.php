<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TimeSlot;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityGenerator
{
    /**
     * @return Collection<int, TimeSlot>
     */
    public function getSlotsForDate(Service $service, Carbon $date): Collection
    {
        if ($date->isWeekend()) {
            return collect();
        }

        $startOfDay = CarbonImmutable::parse($date)->setTime(9, 0);
        $endOfDay = CarbonImmutable::parse($date)->setTime(17, 0);

        $slots = collect();
        $current = $startOfDay;

        while ($current->addMinutes($service->duration_minutes)->lte($endOfDay)) {
            $end = $current->addMinutes($service->duration_minutes);
            
            $slots->push(new TimeSlot($current, $end));

            $current = $end;
        }

        return $slots;
    }
}

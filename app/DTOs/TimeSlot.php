<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\CarbonImmutable;

class TimeSlot
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}
}

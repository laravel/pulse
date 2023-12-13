<?php

namespace Laravel\Pulse\Events;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;

class IsolatedBeat
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public CarbonImmutable $time,
        public ?CarbonInterval $interval,
    ) {
        //
    }
}

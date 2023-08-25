<?php

namespace Laravel\Pulse\Events;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;

class Beat
{
    /**
     * Create a new event instance.
     */
    public function __construct(public CarbonImmutable $time, public CarbonInterval $interval)
    {
        //
    }
}

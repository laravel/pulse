<?php

namespace Laravel\Pulse\Events;

use Carbon\CarbonImmutable;

class SharedBeat
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public CarbonImmutable $time,
        public string $instance,
    ) {
        //
    }
}

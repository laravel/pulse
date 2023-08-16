<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface SupportsServers
{
    /**
     * Retrieve the exceptions.
     */
    public function servers(Interval $interval): Collection;
}

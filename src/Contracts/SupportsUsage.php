<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface SupportsUsage
{
    public function usage(Interval $interval, string $type): Collection;
}

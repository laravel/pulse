<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface SupportsSlowRoutes
{
    public function slowRoutes(Interval $interval): Collection;
}

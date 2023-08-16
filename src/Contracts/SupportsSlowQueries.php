<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface SupportsSlowQueries
{
    public function slowQueries(Interval $interval): Collection;
}

<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface SupportsExceptions
{
    /**
     * Retrieve the exceptions.
     *
     * @param  'last_occurrence'|'count'  $orderBy
     * @return \Illuminate\Support\Collection<int, array{class: string, location: string, count: int, last_occurrence: string}>
     */
    public function exceptions(Interval $interval, string $orderBy): Collection;
}

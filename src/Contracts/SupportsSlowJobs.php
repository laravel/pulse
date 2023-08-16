<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface SupportsSlowJobs
{
    /**
     * Retrieve the slow jobs.
     *
     * @return \Illuminate\Support\Collection<int, array{job: string, count: int, slowest: int}>
     */
    public function slowJobs(Interval $interval): Collection;
}

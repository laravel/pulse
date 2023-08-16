<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface SupportsSlowOutgoingRequests
{
    /**
     * Retrieve the slow outgoing requests.
     *
     * @return \Illuminate\Support\Collection<int, array{uri: string, count: int, slowest: int}>
     */
    public function slowOutgoingRequests(Interval $interval): Collection;
}

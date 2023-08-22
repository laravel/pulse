<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

/**
 * @interval
 */
class SlowOutgoingRequests
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Connection $connection,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        return $this->connection->table('pulse_outgoing_requests')
            ->selectRaw('`uri`, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>=', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $this->config->get('pulse.slow_outgoing_request_threshold'))
            ->groupBy('uri')
            ->orderByDesc('slowest')
            ->get();
    }
}

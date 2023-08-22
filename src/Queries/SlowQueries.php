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
class SlowQueries
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

        return $this->connection->table('pulse_queries')
            ->selectRaw('`sql`, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>=', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $this->config->get('pulse.slow_query_threshold'))
            ->groupBy('sql')
            ->orderByDesc('slowest')
            ->get();
    }
}

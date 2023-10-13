<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * @internal
 */
class SlowQueries
{
    use Concerns\InteractsWithConnection;

    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Repository $config,
        protected DatabaseManager $db,
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

        return $this->connection()->table('pulse_slow_queries')
            ->selectRaw('MAX(`sql`) AS `sql`, COUNT(*) AS `count`, MAX(`duration`) AS `slowest`')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('sql_hash')
            ->orderByDesc('slowest')
            ->get();
    }
}

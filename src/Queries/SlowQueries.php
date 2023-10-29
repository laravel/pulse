<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
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

        return $this->connection()->query()->select([
            'count',
            'slowest',
            'sql' => fn (Builder $query) => $query->select('sql')
                ->from('pulse_slow_queries', as: 'child1')
                ->whereRaw('`child1`.`sql_location_hash` = `parent`.`sql_location_hash`')
                ->limit(1),
            'location' => fn (Builder $query) => $query->select('location')
                ->from('pulse_slow_queries', as: 'child2')
                ->whereRaw('`child2`.`sql_location_hash` = `parent`.`sql_location_hash`')
                ->limit(1),
        ])->fromSub(fn (Builder $query) => $query->selectRaw('`sql_location_hash`, MAX(`duration`) as `slowest`, COUNT(*) as `count`')
            ->from('pulse_slow_queries')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('sql_location_hash')
            ->orderByDesc('slowest')
            ->orderByDesc('count')
            ->limit(101), as: 'parent')
            ->get();
    }
}

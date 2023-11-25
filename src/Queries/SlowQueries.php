<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class SlowQueries
{
    /**
     * Create a new query instance.
     */
    public function __construct(protected DatabaseConnectionResolver $db)
    {
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

        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period; // TODO: Fix for all periods
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period; // TODO: fix for all periods
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('sql')
            ->selectRaw('max(`slowest`) as `slowest`')
            ->selectRaw('sum(`count`) as `count`')
            ->fromSub(fn (Builder $query) => $query
                // tail
                ->select('key as sql')
                ->selectRaw('max(`value`) as `slowest`')
                ->selectRaw('count(*) as `count`')
                ->from('pulse_entries')
                ->where('type', 'slow_query')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as sql')
                    ->selectRaw('max(`value`) as `slowest`')
                    ->selectRaw('sum(`count`) as `count`')
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'slow_query:max')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('sql')
            ->orderByDesc('slowest')
            ->limit(101)
            ->get()
            ->map(function ($row) {
                if (str_contains($row->sql, '::')) {
                    $row->location = Str::afterLast($row->sql, '::');
                    $row->sql = Str::beforeLast($row->sql, '::');
                } else {
                    $row->location = null;
                }

                return $row;
            });
    }
}

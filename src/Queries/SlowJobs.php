<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class SlowJobs
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
            ->select('job')
            ->selectRaw('max(`slowest`) as `slowest`')
            ->selectRaw('sum(`count`) as `count`')
            ->fromSub(fn (Builder $query) => $query
                // tail
                ->select('key as job')
                ->selectRaw('max(`value`) as `slowest`')
                ->selectRaw('count(*) as `count`')
                ->from('pulse_entries')
                ->where('type', 'slow_job')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as job')
                    ->selectRaw('max(`value`) as `slowest`')
                    ->selectRaw('sum(`count`) as `count`')
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'slow_job:max')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('job')
            ->orderByDesc('slowest')
            ->limit(101)
            ->get();
    }
}

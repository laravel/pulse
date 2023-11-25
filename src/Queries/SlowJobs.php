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
            ->select('job', $this->db->connection()->raw('max(`slowest`) as `slowest`'), $this->db->connection()->raw('sum(`count`) as `count`'))
            ->fromSub(fn (Builder $query) => $query
                // duration tail
                ->select('key as job', $this->db->connection()->raw('max(`value`) as `slowest`'), $this->db->connection()->raw('0 as `count`'))
                ->from('pulse_entries')
                ->where('type', 'slow_job')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // count tail
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as job', $this->db->connection()->raw('0 as `slowest`'), $this->db->connection()->raw('count(*) as `count`'))
                    ->from('pulse_entries')
                    ->where('type', 'slow_job')
                    ->where('timestamp', '>=', $tailStart)
                    ->where('timestamp', '<=', $tailEnd)
                    ->groupBy('key')
                )
                // duration buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as job', $this->db->connection()->raw('max(`value`) as `slowest`'), $this->db->connection()->raw('0 as `count`'))
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'slow_job:max')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                )
                // count buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as job', $this->db->connection()->raw('0 as `slowest`'), $this->db->connection()->raw('sum(`value`) as `count`'))
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'slow_job:count')
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

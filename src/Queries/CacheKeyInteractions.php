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
class CacheKeyInteractions
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
     * @return \Illuminate\Support\Collection<string, object>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable();

        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('key', $this->db->connection()->raw('sum(`hits`) as `hits`'), $this->db->connection()->raw('sum(`misses`) as `misses`'))
            ->fromSub(fn (Builder $query) => $query
                // hits tail
                ->select('key', $this->db->connection()->raw('count(*) as `hits`'), $this->db->connection()->raw('0 as `misses`'))
                ->from('pulse_entries')
                ->where('type', 'cache_hit')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // misses tail
                ->unionAll(fn (Builder $query) => $query
                    ->select('key', $this->db->connection()->raw('0 as `hits`'), $this->db->connection()->raw('count(*) as `misses`'))
                    ->from('pulse_entries')
                    ->where('type', 'cache_miss')
                    ->where('timestamp', '>=', $tailStart)
                    ->where('timestamp', '<=', $tailEnd)
                    ->groupBy('key')
                )
                // hits buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key', $this->db->connection()->raw('sum(`value`) as `hits`'), $this->db->connection()->raw('0 as `misses`'))
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'cache_hit:count')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                )
                // misses buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key', $this->db->connection()->raw('0 as `hits`'), $this->db->connection()->raw('sum(`value`) as `misses`'))
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'cache_miss:count')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('key')
            ->orderByDesc('hits') // or misses? Was previously ordered by total count..
            ->limit(101)
            ->get();
    }
}

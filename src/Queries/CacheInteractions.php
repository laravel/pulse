<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Query\Builder;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class CacheInteractions
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
     */
    public function __invoke(Interval $interval): object
    {
        $now = new CarbonImmutable();

        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        $cache = $this->db->connection()->query()
            ->select($this->db->connection()->raw('sum(`hits`) as `hits`'), $this->db->connection()->raw('sum(`misses`) as `misses`'))
            ->fromSub(fn (Builder $query) => $query
                // hits tail
                ->select($this->db->connection()->raw('count(*) as `hits`'), $this->db->connection()->raw('0 as `misses`'))
                ->from('pulse_entries')
                ->where('type', 'cache_hit')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                // misses tail
                ->unionAll(fn (Builder $query) => $query
                    ->select($this->db->connection()->raw('0 as `hits`'), $this->db->connection()->raw('count(*) as `misses`'))
                    ->from('pulse_entries')
                    ->where('type', 'cache_miss')
                    ->where('timestamp', '>=', $tailStart)
                    ->where('timestamp', '<=', $tailEnd)
                )
                // hits buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select($this->db->connection()->raw('sum(`value`) as `hits`'), $this->db->connection()->raw('0 as `misses`'))
                    ->from('pulse_aggregates')
                    ->where('period', $interval->totalSeconds / $period)
                    ->where('type', 'cache_hit:count')
                    ->where('bucket', '>=', $oldestBucket)
                )
                // misses buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select($this->db->connection()->raw('0 as `hits`'), $this->db->connection()->raw('sum(`value`) as `misses`'))
                    ->from('pulse_aggregates')
                    ->where('period', $interval->totalSeconds / $period)
                    ->where('type', 'cache_miss:count')
                    ->where('bucket', '>=', $oldestBucket)
                ), as: 'child'
            )
            ->first();

        return (object) [
            'hits' => (int) $cache?->hits ?? 0,
            'misses' => (int) $cache?->misses ?? 0,
        ];
    }
}

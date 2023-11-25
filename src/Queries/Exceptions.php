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
class Exceptions
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
     * @param  'last_occurrence'|'count'  $orderBy
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function __invoke(Interval $interval, string $orderBy): Collection
    {
        $now = new CarbonImmutable;

        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('key as class', $this->db->connection()->raw('max(`latest`) as `latest`'), $this->db->connection()->raw('sum(`count`) as `count`'))
            ->fromSub(fn (Builder $query) => $query
                // latest
                ->select('key', $this->db->connection()->raw('`value` as `latest`'), $this->db->connection()->raw('0 as `count`'))
                ->from('pulse_values')
                ->where('type', 'exception:latest')
                // count tail
                ->unionAll(fn (Builder $query) => $query
                    ->select('key', $this->db->connection()->raw('0 as `latest`'), $this->db->connection()->raw('count(*) as `count`'))
                    ->from('pulse_entries')
                    ->where('type', 'exception')
                    ->where('timestamp', '>=', $tailStart)
                    ->where('timestamp', '<=', $tailEnd)
                    ->groupBy('key')
                )
                // count buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key', $this->db->connection()->raw('0 as `latest`'), $this->db->connection()->raw('sum(`value`) as `count`'))
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'exception:count')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('key')
            ->orderByDesc($orderBy) // TODO: SQL injection?
            ->limit(101)
            ->get()
            ->map(function ($row) {
                if (str_contains($row->class, '::')) {
                    $row->location = Str::afterLast($row->class, '::');
                    $row->class = Str::beforeLast($row->class, '::');
                } else {
                    $row->location = null;
                }

                $row->latest = (int) $row->latest;
                $row->count = (int) $row->count;

                return $row;
            });
    }
}

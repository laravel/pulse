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
            ->select('class')
            ->selectRaw('max(`latest`) as `latest`')
            ->selectRaw('sum(`count`) as `count`')
            ->fromSub(fn (Builder $query) => $query
                // latest
                ->select('key as class')
                ->selectRaw('`value` as `latest`')
                ->selectRaw('0 as `count`')
                ->from('pulse_values')
                ->where('type', 'exception:latest')
                ->where('value', '>=', $windowStart)
                // count tail
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as class')
                    ->selectRaw('0 as `latest`')
                    ->selectRaw('count(*) as `count`')
                    ->from('pulse_entries')
                    ->where('type', 'exception')
                    ->where('timestamp', '>=', $tailStart)
                    ->where('timestamp', '<=', $tailEnd)
                    ->groupBy('key')
                )
                // count buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as class')
                    ->selectRaw('0 as `latest`')
                    ->selectRaw('sum(`value`) as `count`')
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'exception:sum')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('class')
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

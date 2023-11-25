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
class SlowOutgoingRequests
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
     * @return \Illuminate\Support\Collection<int, object{
     *     method: string,
     *     uri: string,
     *     count: int,
     *     slowest: int
     * }>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('uri')
            ->selectRaw('max(`slowest`) as `slowest`')
            ->selectRaw('sum(`count`) as `count`')
            ->fromSub(fn (Builder $query) => $query
                // tail
                ->select('key as uri')
                ->selectRaw('max(`value`) as `slowest`')
                ->selectRaw('count(*) as `count`')
                ->from('pulse_entries')
                ->where('type', 'slow_outgoing_request')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as uri')
                    ->selectRaw('max(`value`) as `slowest`')
                    ->selectRaw('sum(`count`) as `count`')
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'slow_outgoing_request:max')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('uri')
            ->orderByDesc('slowest')
            ->limit(101)
            ->get()
            ->map(function ($row) {
                [$method, $uri] = explode(' ', $row->uri, 2);

                return (object) [
                    'method' => (string) $method,
                    'uri' => (string) $uri,
                    'count' => (int) $row->count,
                    'slowest' => (int) $row->slowest,
                ];
            });
    }
}

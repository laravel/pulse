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
    public function __construct(
        protected DatabaseConnectionResolver $db,
    ) {
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
            ->select('uri', $this->db->connection()->raw('max(`slowest`) as `slowest`'), $this->db->connection()->raw('sum(`count`) as `count`'))
            ->fromSub(fn (Builder $query) => $query
                // duration tail
                ->select('key as uri', $this->db->connection()->raw('max(`value`) as `slowest`'), $this->db->connection()->raw('0 as `count`'))
                ->from('pulse_entries')
                ->where('type', 'slow_outgoing_request')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // count tail
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as uri', $this->db->connection()->raw('0 as `slowest`'), $this->db->connection()->raw('count(*) as `count`'))
                    ->from('pulse_entries')
                    ->where('type', 'slow_outgoing_request')
                    ->where('timestamp', '>=', $tailStart)
                    ->where('timestamp', '<=', $tailEnd)
                    ->groupBy('key')
                )
                // duration buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as uri', $this->db->connection()->raw('max(`value`) as `slowest`'), $this->db->connection()->raw('0 as `count`'))
                    ->from('pulse_aggregates')
                    ->where('period', $interval->totalSeconds / $period)
                    ->where('type', 'slow_outgoing_request:max')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                )
                // count buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as uri', $this->db->connection()->raw('0 as `slowest`'), $this->db->connection()->raw('sum(`value`) as `count`'))
                    ->from('pulse_aggregates')
                    ->where('period', $interval->totalSeconds / $period)
                    ->where('type', 'slow_outgoing_request:count')
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

<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Laravel\Pulse\Concerns\InteractsWithDatabaseConnection;
use stdClass;

/**
 * @internal
 */
class SlowOutgoingRequests
{
    use InteractsWithDatabaseConnection;

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

        return $this->db()->query()->select([
            'count',
            'slowest',
            'uri' => fn (Builder $query) => $query->select('uri')
                ->from('pulse_outgoing_requests', as: 'child')
                ->whereRaw('`child`.`uri_hash` = `parent`.`uri_hash`')
                ->limit(1),
        ])->fromSub(fn (Builder $query) => $query->selectRaw('`uri_hash`, MAX(`duration`) as `slowest`, COUNT(*) as `count`')
            ->from('pulse_outgoing_requests')
            ->where('slow', true)
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('uri_hash')
            ->orderByDesc('slowest')
            ->orderByDesc('count')
            ->limit(101), as: 'parent')
            ->get()
            ->map(function (stdClass $row) {
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

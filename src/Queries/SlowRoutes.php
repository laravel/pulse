<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Query\Builder;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class SlowRoutes
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected DatabaseConnectionResolver $db,
        protected Router $router,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<int, object{
     *     method: string,
     *     uri: string,
     *     action: ?string,
     *     count: int,
     *     slowest: int
     * }>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        $routes = $this->router->getRoutes()->getRoutesByMethod();

        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('route', $this->db->connection()->raw('max(`slowest`) as `slowest`'), $this->db->connection()->raw('sum(`count`) as `count`'))
            ->fromSub(fn (Builder $query) => $query
                // duration tail
                ->select('key as route', $this->db->connection()->raw('max(`value`) as `slowest`'), $this->db->connection()->raw('0 as `count`'))
                ->from('pulse_entries')
                ->where('type', 'slow_request')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // count tail
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as route', $this->db->connection()->raw('0 as `slowest`'), $this->db->connection()->raw('count(*) as `count`'))
                    ->from('pulse_entries')
                    ->where('type', 'slow_request')
                    ->where('timestamp', '>=', $tailStart)
                    ->where('timestamp', '<=', $tailEnd)
                    ->groupBy('key')
                )
                // duration buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as route', $this->db->connection()->raw('max(`value`) as `slowest`'), $this->db->connection()->raw('0 as `count`'))
                    ->from('pulse_aggregates')
                    ->where('period', $interval->totalSeconds / $period)
                    ->where('type', 'slow_request:max')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                )
                // count buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as route', $this->db->connection()->raw('0 as `slowest`'), $this->db->connection()->raw('sum(`value`) as `count`'))
                    ->from('pulse_aggregates')
                    ->where('period', $interval->totalSeconds / $period)
                    ->where('type', 'slow_request:count')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('route')
            ->orderByDesc('slowest')
            ->limit(101)
            ->get()
            ->map(function ($row) use ($routes) {
                [$method, $uri] = explode(' ', $row->route, 2);

                preg_match('/(.*?)(?:\s\((.*)\))?$/', $uri, $matches);

                [$uri, $via] = [$matches[1], $matches[2] ?? null];

                $domain = Str::before($uri, '/');

                if ($domain) {
                    $uri = '/'.Str::after($uri, '/');
                }

                if ($via) {
                    $action = 'via '.$via;
                } else {
                    $path = $uri === '/' ? $uri : ltrim($uri, '/');
                    $action = ($route = $routes[$method][$domain.$path] ?? null) ? (string) $route->getActionName() : null;
                }

                return (object) [
                    'uri' => $domain.$uri,
                    'method' => $method,
                    'action' => $action,
                    'count' => (int) $row->count,
                    'slowest' => (int) $row->slowest,
                ];
            });
    }
}

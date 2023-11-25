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
class SlowRequests
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
        $now = CarbonImmutable::now();

        $routes = $this->router->getRoutes()->getRoutesByMethod();

        $period = (int) ($interval->totalSeconds / 60);
        $windowStart = (int) ($now->getTimestamp() - $interval->totalSeconds + 1);
        $currentBucket = (int) floor($now->getTimestamp() / $period) * $period;
        $oldestBucket = (int) ($currentBucket - $interval->totalSeconds + $period);
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('route')
            ->selectRaw('max(`slowest`) as `slowest`')
            ->selectRaw('sum(`count`) as `count`')
            ->fromSub(fn (Builder $query) => $query
                // duration tail
                ->select('key as route')
                ->selectRaw('max(`value`) as `slowest`')
                ->selectRaw('count(*) as `count`')
                ->from('pulse_entries')
                ->where('type', 'slow_request')
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key as route')
                    ->selectRaw('max(`value`) as `slowest`')
                    ->selectRaw('sum(`count`) as `count`')
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', 'slow_request:max')
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

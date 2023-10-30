<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;

/**
 * @internal
 */
class SlowRoutes
{
    use Concerns\InteractsWithConnection;

    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Repository $config,
        protected DatabaseManager $db,
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

        return $this->connection()->query()->select([
            'count',
            'slowest',
            'route' => fn (Builder $query) => $query->select('route')
                ->from('pulse_requests', as: 'child')
                ->whereRaw('`child`.`route_hash` = `parent`.`route_hash`')
                ->limit(1),
        ])->fromSub(fn (Builder $query) => $query->selectRaw('`route_hash`, MAX(`duration`) as `slowest`, COUNT(*) as `count`')
            ->from('pulse_requests')
            ->where('slow', true)
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('route_hash')
            ->orderByDesc('slowest')
            ->orderByDesc('count')
            ->limit(101), as: 'parent')
            ->get()
            ->map(function (stdClass $row) use ($routes) {
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

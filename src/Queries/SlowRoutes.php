<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
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
     *     route: string,
     *     action: ?string,
     *     count: int,
     *     slowest: int
     * }>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        return $this->connection()->query()->select([
            'count',
            'slowest',
            'route' => fn (Builder $query) => $query->select('route')
                ->from('pulse_requests', as: 'child')
                ->whereRaw('`child`.`route_hash` = `parent`.`route_hash`')
                ->limit(1),
        ])->fromSub(fn (Builder $query) => $query->selectRaw('`route_hash`, MAX(`duration`) as `slowest`, COUNT(*) as `count`')
            ->from('pulse_requests')
            ->where('reached_threshold', true)
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('route_hash')
            ->orderByDesc('slowest')
            ->orderByDesc('count')
            ->limit(100), as: 'parent')
            ->get()
            ->map(fn (stdClass $row) => (object) [
                'route' => (string) $row->route,
                'action' => with(explode(' ', $row->route, 2), function (array $parts) {
                    [$method, $path] = $parts;
                    $path = ltrim($path, '/');

                    return ($this->router->getRoutes()->get($method)[$path] ?? null)?->getActionName();
                }),
                'count' => (int) $row->count,
                'slowest' => (int) $row->slowest,
            ]);
    }
}

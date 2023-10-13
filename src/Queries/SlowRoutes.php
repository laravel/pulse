<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Laravel\Pulse\Recorders\Requests;
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
     * @return \Illuminate\Support\Collection<int, array{uri: string, action: ?string, request_count: int, slowest_duration: int}>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        return $this->connection()->table('pulse_requests')
            ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $this->config->get('pulse.recorders.'.Requests::class.'.threshold'))
            ->groupBy('route')
            ->orderByDesc('slowest')
            ->get()
            ->map(fn (stdClass $row) => [
                'uri' => $row->route,
                'action' => with(explode(' ', $row->route, 2), function (array $parts) {
                    [$method, $path] = $parts;

                    return ($this->router->getRoutes()->get($method)[$path] ?? null)?->getActionName();
                }),
                'request_count' => (int) $row->count,
                'slowest_duration' => (int) $row->slowest,
            ]);
    }
}

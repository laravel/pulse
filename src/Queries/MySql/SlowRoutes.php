<?php

namespace Laravel\Pulse\Queries\MySql;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entries\Table;

/**
 * @interval
 */
class SlowRoutes
{
    public function __invoke(Connection $connection, Interval $interval, callable $routeResolver, int $threshold): Collection
    {
        return $connection->table(Table::Request->value)
            ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>=', (new CarbonImmutable)->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $threshold)
            ->groupBy('route')
            ->orderByDesc('slowest')
            ->get()
            ->map(fn ($row) => [
                'uri' => $row->route,
                'action' => $routeResolver(...explode(' ', $row->route, 2))?->getActionName(),
                'request_count' => (int) $row->count,
                'slowest_duration' => (int) $row->slowest,
            ]);
    }
}

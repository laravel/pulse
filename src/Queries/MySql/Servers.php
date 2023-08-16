<?php

namespace Laravel\Pulse\Queries\MySql;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Table;

/**
 * @interval
 */
class Servers
{
    /**
     * Run the query.
     *
     * @param  'max'|'average'  $aggregation
     */
    public function __invoke(Connection $connection, Interval $interval, string $aggregation): Collection
    {
        $maxDataPoints = 60;

        $now = new CarbonImmutable;

        $currentBucket = CarbonImmutable::createFromTimestamp(
            floor($now->getTimestamp() / ($interval->totalSeconds / $maxDataPoints)) * ($interval->totalSeconds / $maxDataPoints)
        );

        $secondsPerPeriod = $interval->totalSeconds / $maxDataPoints;

        $padding = collect([])
            ->pad(60, null)
            ->map(fn ($value, $i) => (object) [
                'date' => $currentBucket->subSeconds($i * $secondsPerPeriod)->format('Y-m-d H:i'),
                'cpu_percent' => null,
                'memory_used' => null,
            ])
            ->reverse()
            ->keyBy('date');

        $serverReadings = $connection->query()
            ->select('bucket', 'server')
            ->when(true, fn ($query) => match ($aggregation) {
                'max' => $query
                    ->selectRaw('ROUND(MAX(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(MAX(`memory_used`)) AS `memory_used`'),
                default => $query
                    ->selectRaw('ROUND(AVG(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(AVG(`memory_used`)) AS `memory_used`')
            })
            ->fromSub(
                fn ($query) => $query
                    ->from(Table::Server->value)
                    ->select(['server', 'cpu_percent', 'memory_used', 'date'])
                    // Divide the data into buckets.
                    ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`date`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                    ->where('date', '>=', $now->subSeconds((int) $interval->totalSeconds)),
                'grouped'
            )
            ->groupBy('server', 'bucket')
            ->orderByDesc('bucket')
            ->limit($maxDataPoints)
            ->get()
            ->reverse()
            ->groupBy('server')
            ->map(function ($readings) use ($secondsPerPeriod, $padding) {
                $readings = $readings->keyBy(fn ($reading) => CarbonImmutable::createFromTimestamp($reading->bucket * $secondsPerPeriod)->format('Y-m-d H:i'));

                return $padding->merge($readings)->values();
            });

        return $connection->table(Table::Server->value)
            // Get the latest row for every server, even if it hasn't reported in the selected period.
            ->joinSub(
                $connection->table(Table::Server->value)
                    ->selectRaw('server, MAX(date) AS date')
                    ->groupBy('server'),
                'grouped',
                fn ($join) => $join
                    ->on(Table::Server->value.'.server', '=', 'grouped.server')
                    ->on(Table::Server->value.'.date', '=', 'grouped.date')
            )
            ->get()
            ->map(fn ($server) => (object) [
                'name' => $server->server,
                'slug' => Str::slug($server->server),
                'cpu_percent' => $server->cpu_percent,
                'memory_used' => $server->memory_used,
                'memory_total' => $server->memory_total,
                'storage' => json_decode($server->storage, flags: JSON_THROW_ON_ERROR),
                'readings' => $serverReadings->get($server->server)?->map(fn ($reading) => (object) [
                    'cpu_percent' => $reading->cpu_percent,
                    'memory_used' => $reading->memory_used,
                ])->all() ?? [],
                'updated_at' => $updatedAt = CarbonImmutable::parse($server->date),
                'recently_reported' => $updatedAt->isAfter($now->subSeconds(30)),
            ])
            ->keyBy('slug');
    }
}

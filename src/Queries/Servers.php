<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @interval
 */
class Servers
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Connection $connection,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Run the query.
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        $maxDataPoints = 60;

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

        $serverReadings = $this->connection->query()
            ->select('bucket', 'server')
            ->when(true, fn ($query) => match ($this->config->get('pulse.graph_aggregation')) {
                'max' => $query
                    ->selectRaw('ROUND(MAX(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(MAX(`memory_used`)) AS `memory_used`'),
                default => $query
                    ->selectRaw('ROUND(AVG(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(AVG(`memory_used`)) AS `memory_used`')
            })
            ->fromSub(
                fn ($query) => $query
                    ->from('pulse_servers')
                    ->select(['server', 'cpu_percent', 'memory_used', 'date'])
                    // Divide the data into buckets.
                    ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`date`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                    ->where('date', '>=', $now->subSeconds($interval->totalSeconds)),
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

        return $this->connection->table('pulse_servers')
            // Get the latest row for every server, even if it hasn't reported in the selected period.
            ->joinSub(
                $this->connection->table('pulse_servers')
                    ->selectRaw('server, MAX(date) AS date')
                    ->groupBy('server'),
                'grouped',
                fn ($join) => $join
                    ->on('pulse_servers'.'.server', '=', 'grouped.server')
                    ->on('pulse_servers'.'.date', '=', 'grouped.date')
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

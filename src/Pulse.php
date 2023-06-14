<?php

namespace Laravel\Pulse;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Pulse
{
    /**
     * Indicates if Pulse migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    public bool $doNotReportUsage = false;

    public function servers()
    {
        // TODO: Exclude servers that haven't reported recently?
        return collect(RedisAdapter::hgetall('pulse_servers'))
            ->map(function ($name, $slug) {
                $readings = collect(RedisAdapter::xrange("pulse_servers:{$slug}", '-', '+'))
                    ->map(fn ($server) => [
                        'timestamp' => (int) $server['timestamp'],
                        'cpu' => (int) $server['cpu'],
                        'memory_used' => (int) $server['memory_used'],
                        'memory_total' => (int) $server['memory_total'],
                        'storage' => json_decode($server['storage']),
                    ])
                    ->values();

                if ($readings->isEmpty()) {
                    return null;
                }

                return [
                    'name' => $name,
                    'readings' => $readings,
                ];
            })
            ->filter();
    }

    public function slowQueries()
    {
        // TODO: Do we want to rebuild this on every request?
        RedisAdapter::zunionstore(
            'pulse_slow_query_execution_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_query_execution_counts:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'SUM'
        );

        RedisAdapter::zunionstore(
            'pulse_slow_query_total_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_query_total_durations:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'SUM'
        );

        RedisAdapter::zunionstore(
            'pulse_slow_query_slowest_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_query_slowest_durations:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'MAX'
        );

        $executionCounts = RedisAdapter::zrevrange('pulse_slow_query_execution_counts:7-day', 0, -1, true);
        $totalDurations = RedisAdapter::zrevrange('pulse_slow_query_total_durations:7-day', 0, -1, true);
        $slowestDurations = RedisAdapter::zrevrange('pulse_slow_query_slowest_durations:7-day', 0, -1, true);

        return collect($executionCounts)
            ->map(function ($executionCount, $sql) use ($totalDurations, $slowestDurations) {
                return [
                    'sql' => $sql,
                    'execution_count' => (int) $executionCount,
                    'slowest_duration' => isset($slowestDurations[$sql]) ? (int) $slowestDurations[$sql] : null,
                    'average_duration' => isset($totalDurations[$sql]) ? (int) round($totalDurations[$sql] / $executionCount) : null,
                ];
            })
            ->values();
    }

    public function cacheStats()
    {
        $hits = collect(range(0, 6))
            ->map(fn ($days) => RedisAdapter::get('pulse_cache_hits:'.now()->subDays($days)->format('Y-m-d')))
            ->sum();

        $misses = collect(range(0, 6))
            ->map(fn ($days) => RedisAdapter::get('pulse_cache_misses:'.now()->subDays($days)->format('Y-m-d')))
            ->sum();

        $total = $hits + $misses;

        if ($total === 0) {
            $rate = 0;
        } else {
            $rate = (int) (($hits / $total) * 100);
        }

        return [
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $rate,
        ];
    }

    public function exceptions()
    {
        RedisAdapter::zunionstore(
            'pulse_exception_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_exception_counts:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'SUM'
        );

        RedisAdapter::zunionstore(
            'pulse_exception_last_occurrences:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_exception_last_occurrences:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'MAX'
        );

        $exceptionCounts = RedisAdapter::zrevrange('pulse_exception_counts:7-day', 0, -1, true);
        $exceptionLastOccurrences = RedisAdapter::zrevrange('pulse_exception_last_occurrences:7-day', 0, -1, true);

        return collect($exceptionCounts)
            ->map(fn ($count, $exception) => [
                ...json_decode($exception, true),
                'count' => $count,
                'last_occurrence' => isset($exceptionLastOccurrences[$exception]) ? (int) $exceptionLastOccurrences[$exception] : null,
            ])
            ->values();
    }

    public function queues()
    {
        return collect(config('pulse.queues'))->map(fn ($queue) => [
            'queue' => $queue,
            'size' => Queue::size($queue),
            'failed' => collect(app('queue.failer')->all())->filter(fn ($job) => $job->queue === $queue)->count(),
        ]);
    }

    public function css()
    {
        return file_get_contents(__DIR__.'/../dist/pulse.css');
    }

    public function js()
    {
        return file_get_contents(__DIR__.'/../dist/pulse.js');
    }

    /**
     * Configure Pulse to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }
}

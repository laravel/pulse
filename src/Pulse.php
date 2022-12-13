<?php

namespace Laravel\Pulse;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Pulse
{
    public function servers()
    {
        return collect(Redis::hGetAll('pulse_servers'))
            ->map(function ($name, $slug) {
                $readings = collect(Redis::xRange("pulse_servers:{$slug}", '-', '+'))
                    ->map(fn ($server) => [
                        'cpu' => (int) $server['cpu'],
                        'memory_used' => (int) $server['memory_used'],
                        'memory_total' => (int) $server['memory_total'],
                        'storage' => json_decode($server['storage'])
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
            ->filter()
            ->values();
    }

    public function userRequestCounts()
    {
        // TODO: We don't need to rebuild this on every request - maybe once per hour?
        Redis::zUnionStore(
            'pulse_user_request_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_user_request_counts:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray()
        );

        $scores = collect(Redis::zRevRange('pulse_user_request_counts:7-day', 0, 9, ['WITHSCORES' => true]));

        $minutesElapsedToday = now()->diffInMinutes(now()->startOfDay());
        $days = 6 + ($minutesElapsedToday / (24 * 60));

        $users = User::findMany($scores->keys());

        return collect($scores)
            ->map(function ($score, $userId) use ($users, $days) {
                $user = $users->firstWhere('id', $userId);

                return $user ? [
                    'daily_average' => floor($score / $days),
                    'user' => $user->setVisible(['name', 'email']),
                ] : null;
            })
            ->filter()
            ->values();
    }

    public function slowEndpoints()
    {
        // TODO: Do we want to rebuild this on every request?
        Redis::zUnionStore(
            'pulse_slow_endpoint_request_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_request_counts:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'SUM']
        );

        Redis::zUnionStore(
            'pulse_slow_endpoint_total_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_total_durations:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'SUM']
        );

        Redis::zUnionStore(
            'pulse_slow_endpoint_slowest_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_slowest_durations:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'MAX']
        );

        $requestCounts = Redis::zRevRange('pulse_slow_endpoint_request_counts:7-day', 0, -1, ['WITHSCORES' => true]);
        $totalDurations = Redis::zRevRange('pulse_slow_endpoint_total_durations:7-day', 0, -1, ['WITHSCORES' => true]);
        $slowestDurations = Redis::zRevRange('pulse_slow_endpoint_slowest_durations:7-day', 0, -1, ['WITHSCORES' => true]);

        return collect($requestCounts)
            ->map(function ($requestCount, $uri) use ($totalDurations, $slowestDurations) {
                $method = substr($uri, 0, strpos($uri, ' '));
                $path = substr($uri, strpos($uri, ' ') + 1);
                $route = Route::getRoutes()->get($method)[$path] ?? null;

                return [
                    'uri' => $method.' '.Str::start($path, '/'),
                    'action' => $route?->getActionName(),
                    'request_count' => (int) $requestCount,
                    'slowest_duration' => (int) $slowestDurations[$uri],
                    'average_duration' => (int) round($totalDurations[$uri] / $requestCount),
                ];
            })
            ->values();
    }

    public function usersExperiencingSlowEndpoints()
    {
        Redis::zUnionStore(
            'pulse_slow_endpoint_user_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_user_counts:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'SUM']
        );

        $userCounts = collect(Redis::zRevRange('pulse_slow_endpoint_user_counts:7-day', 0, -1, ['WITHSCORES' => true]));

        // TODO: polling for this every 2 seconds is not good.
        $users = User::findMany($userCounts->keys());

        return $userCounts
            ->map(function ($count, $userId) use ($users) {
                $user = $users->firstWhere('id', $userId);

                return $user ? [
                    'count' => $count,
                    'user' => $user->setVisible(['name', 'email']),
                ] : null;
            })
            ->filter()
            ->values();
    }

    public function slowQueries()
    {
        // TODO: Do we want to rebuild this on every request?
        Redis::zUnionStore(
            'pulse_slow_query_execution_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_query_execution_counts:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'SUM']
        );

        Redis::zUnionStore(
            'pulse_slow_query_total_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_query_total_durations:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'SUM']
        );

        Redis::zUnionStore(
            'pulse_slow_query_slowest_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_query_slowest_durations:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'MAX']
        );

        $executionCounts = Redis::zRevRange('pulse_slow_query_execution_counts:7-day', 0, -1, ['WITHSCORES' => true]);
        $totalDurations = Redis::zRevRange('pulse_slow_query_total_durations:7-day', 0, -1, ['WITHSCORES' => true]);
        $slowestDurations = Redis::zRevRange('pulse_slow_query_slowest_durations:7-day', 0, -1, ['WITHSCORES' => true]);

        return collect($executionCounts)
            ->map(function ($executionCount, $sql) use ($totalDurations, $slowestDurations) {

                return [
                    'sql' => $sql,
                    'execution_count' => (int) $executionCount,
                    'slowest_duration' => $slowestDurations[$sql],
                    'average_duration' => round($totalDurations[$sql] / $executionCount, 2),
                ];
            })
            ->values();
    }

    public function cacheStats()
    {
        return [
            'hits' => collect(range(0, 6))
                ->map(fn ($days) => Redis::get('pulse_cache_hits:' . now()->subDays($days)->format('Y-m-d')))
                ->sum(),
            'misses' => collect(range(0, 6))
                ->map(fn ($days) => Redis::get('pulse_cache_misses:' . now()->subDays($days)->format('Y-m-d')))
                ->sum(),
        ];
    }

    public function exceptions()
    {
        Redis::zUnionStore(
            'pulse_exception_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_exception_counts:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'SUM']
        );

        Redis::zUnionStore(
            'pulse_exception_last_occurrences:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_exception_last_occurrences:' . now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            ['aggregate' => 'MAX']
        );

        $exceptionCounts = Redis::zRevRange('pulse_exception_counts:7-day', 0, -1, ['WITHSCORES' => true]);
        $exceptionLastOccurrences = Redis::zRevRange('pulse_exception_last_occurrences:7-day', 0, -1, ['WITHSCORES' => true]);

        return collect($exceptionCounts)
            ->map(fn ($count, $exception) => [
                ...json_decode($exception, true),
                'count' => $count,
                'last_occurrence' => $exceptionLastOccurrences[$exception],
            ])
            ->values();
    }

    public function queues()
    {
        return [
            [
                'queue' => 'default',
                'size' => Queue::size('default'),
                'failed' => collect(app('queue.failer')->all())->filter(fn ($job) => $job->queue === 'default')->count(),
            ],
            [
                'queue' => 'high',
                'size' => Queue::size('high'),
                'failed' => collect(app('queue.failer')->all())->filter(fn ($job) => $job->queue === 'high')->count(),
            ]
        ];
    }
}

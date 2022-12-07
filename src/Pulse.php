<?php

namespace Laravel\Pulse;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Pulse
{
    public function servers()
    {
        // TODO: Find all reporting servers and retrieve their data.
        return [
            collect(Redis::xRange('pulse_servers:server-1', '-', '+'))->values(),
        ];
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
                    'uri' => $uri,
                    'action' => $route?->getActionName(),
                    'request_count' => (int) $requestCount,
                    'slowest_duration' => (int) $slowestDurations[$uri],
                    'average_duration' => (int) round($totalDurations[$uri] / $requestCount),
                ];
            })
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
}

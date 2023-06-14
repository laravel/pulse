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

    public function userRequestCounts()
    {
        $from = now()->subHours(1);

        $top10 = DB::table('pulse_requests')
            ->selectRaw('user_id, COUNT(*) as count')
            ->whereNotNull('user_id')
            ->where('date', '>=', $from->toDateTimeString())
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $users = User::findMany($top10->pluck('user_id'));

        return $top10
            ->map(function ($row) use ($users) {
                $user = $users->firstWhere('id', $row->user_id);

                return $user ? [
                    'count' => $row->count,
                    'user' => $user->setVisible(['name', 'email']),
                ] : null;
            })
            ->filter()
            ->values();

        return;

        // TODO: We probably don't need to rebuild this on every request - maybe once per hour?
        RedisAdapter::zunionstore(
            'pulse_user_request_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_user_request_counts:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray()
        );

        $scores = collect(RedisAdapter::zrevrange('pulse_user_request_counts:7-day', 0, 9, true));

        $users = User::findMany($scores->keys());

        return collect($scores)
            ->map(function ($score, $userId) use ($users) {
                $user = $users->firstWhere('id', $userId);

                return $user ? [
                    'count' => $score,
                    // TODO: Make configurable
                    'user' => $user->setVisible(['name', 'email']),
                ] : null;
            })
            ->filter()
            ->values();
    }

    public function slowEndpoints()
    {
        $from = now()->subHours(1);
        $threshold = 1000;

        return DB::table('pulse_requests')
            ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest, AVG(duration) AS average')
            ->where('date', '>=', $from->toDateTimeString())
            ->where('duration', '>=', $threshold)
            ->groupBy('route')
            ->orderByDesc('slowest')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $method = substr($row->route, 0, strpos($row->route, ' '));
                $path = substr($row->route, strpos($row->route, '/') + 1);
                $route = Route::getRoutes()->get($method)[$path] ?? null;

                return [
                    'uri' => $row->route,
                    'action' => $route?->getActionName(),
                    'request_count' => (int) $row->count,
                    'slowest_duration' => (int) $row->slowest,
                    'average_duration' => (int) $row->average,
                ];
            });

        // return;

        // TODO: Do we want to rebuild this on every request?
        RedisAdapter::zunionstore(
            'pulse_slow_endpoint_request_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_request_counts:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'SUM'
        );

        RedisAdapter::zunionstore(
            'pulse_slow_endpoint_total_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_total_durations:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'SUM'
        );

        RedisAdapter::zunionstore(
            'pulse_slow_endpoint_slowest_durations:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_slowest_durations:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'MAX'
        );

        $requestCounts = RedisAdapter::zrevrange('pulse_slow_endpoint_request_counts:7-day', 0, -1, true);
        $totalDurations = RedisAdapter::zrevrange('pulse_slow_endpoint_total_durations:7-day', 0, -1, true);
        $slowestDurations = RedisAdapter::zrevrange('pulse_slow_endpoint_slowest_durations:7-day', 0, -1, true);

        return collect($requestCounts)
            ->map(function ($requestCount, $uri) use ($totalDurations, $slowestDurations) {
                $method = substr($uri, 0, strpos($uri, ' '));
                $path = substr($uri, strpos($uri, ' ') + 1);
                $route = Route::getRoutes()->get($method)[$path] ?? null;

                return [
                    'uri' => $method.' '.Str::start($path, '/'),
                    'action' => $route?->getActionName(),
                    'request_count' => (int) $requestCount,
                    'slowest_duration' => isset($slowestDurations[$uri]) ? (int) $slowestDurations[$uri] : null,
                    'average_duration' => isset($totalDurations[$uri]) ? (int) round($totalDurations[$uri] / $requestCount) : null,
                ];
            })
            ->values();
    }

    public function usersExperiencingSlowEndpoints()
    {
        RedisAdapter::zunionstore(
            'pulse_slow_endpoint_user_counts:7-day',
            collect(range(0, 6))
                ->map(fn ($days) => 'pulse_slow_endpoint_user_counts:'.now()->subDays($days)->format('Y-m-d'))
                ->toArray(),
            'SUM'
        );

        $userCounts = collect(RedisAdapter::zrevrange('pulse_slow_endpoint_user_counts:7-day', 0, -1, true));

        // TODO: polling for this every 2 seconds is probably not great.
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

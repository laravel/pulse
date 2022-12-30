<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Redis;

class HandleQuery
{
    /**
     * Handle the execution of a database query.
     */
    public function __invoke(QueryExecuted $event): void
    {
        if ($event->time < config('pulse.slow_query_threshold')) {
            return;
        }

        // TODO: Capture where the query came from? Won't always be userland.

        $keyDate = now()->format('Y-m-d');
        $keyExpiry = now()->startOfDay()->addDays(7)->timestamp;
        $keyPrefix = config('database.redis.options.prefix');

        $countKey = "pulse_slow_query_execution_counts:{$keyDate}";
        Redis::zIncrBy($countKey, 1, $event->sql);
        Redis::rawCommand('EXPIREAT', $keyPrefix.$countKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7

        $durationKey = "pulse_slow_query_total_durations:{$keyDate}";
        Redis::zIncrBy($durationKey, round($event->time), $event->sql);
        Redis::rawCommand('EXPIREAT', $keyPrefix.$durationKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7

        $slowestKey = "pulse_slow_query_slowest_durations:{$keyDate}";
        Redis::rawCommand('ZADD', $keyPrefix.$slowestKey, 'GT', round($event->time), $event->sql); // TODO: phpredis zAdd doesn't support 'GT' in 5.3.7
        Redis::rawCommand('EXPIREAT', $keyPrefix.$slowestKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7
    }
}

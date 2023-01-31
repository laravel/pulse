<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Pulse\RedisAdapter;

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

        $countKey = "pulse_slow_query_execution_counts:{$keyDate}";
        RedisAdapter::zincrby($countKey, 1, $event->sql);
        RedisAdapter::expireat($countKey, $keyExpiry, 'NX');

        $durationKey = "pulse_slow_query_total_durations:{$keyDate}";
        RedisAdapter::zincrby($durationKey, round($event->time), $event->sql);
        RedisAdapter::expireat($durationKey, $keyExpiry, 'NX');

        $slowestKey = "pulse_slow_query_slowest_durations:{$keyDate}";
        RedisAdapter::zadd($slowestKey, round($event->time), $event->sql, 'GT');
        RedisAdapter::expireat($slowestKey, $keyExpiry, 'NX');
    }
}

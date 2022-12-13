<?php

namespace Laravel\Pulse\Handlers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class HandleHttpRequest
{
    /**
     * Handle the completion of an HTTP request.
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        $duration = $startedAt->diffInMilliseconds(now());
        $route = $request->method().' '.($request->route()?->uri() ?? $request->path());

        $keyDate = $startedAt->format('Y-m-d');
        $keyExpiry = $startedAt->toImmutable()->startOfDay()->addDays(7)->timestamp;
        $keyPrefix = config('database.redis.options.prefix');

        // Slow endpoint
        if ($duration >= config('pulse.slow_endpoint_threshold')) {
            $countKey = "pulse_slow_endpoint_request_counts:{$keyDate}";
            Redis::zIncrBy($countKey, 1, $route);
            Redis::rawCommand('EXPIREAT', $keyPrefix.$countKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7

            $durationKey = "pulse_slow_endpoint_total_durations:{$keyDate}";
            Redis::zIncrBy($durationKey, $duration, $route);
            Redis::rawCommand('EXPIREAT', $keyPrefix.$durationKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7

            $slowestKey = "pulse_slow_endpoint_slowest_durations:{$keyDate}";
            Redis::rawCommand('ZADD', $keyPrefix.$slowestKey, 'GT', $duration, $route); // TODO: phpredis zAdd doesn't support 'GT' in 5.3.7
            Redis::rawCommand('EXPIREAT', $keyPrefix.$slowestKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7

            if ($request->user()) {
                $userKey = "pulse_slow_endpoint_user_counts:{$keyDate}";
                Redis::zIncrBy($userKey, 1, $request->user()->id);
                Redis::rawCommand('EXPIREAT', $keyPrefix.$userKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7
            }
        }

        // Top 10 users hitting the application
        // TODO: Improve Dashboard ignoring
        if ($request->user() && $request->route()?->uri() !== 'pulse') {
            $hitsKey = "pulse_user_request_counts:{$keyDate}";
            Redis::zIncrBy($hitsKey, 1, $request->user()->id);
            Redis::rawCommand('EXPIREAT', $keyPrefix.$hitsKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7
        }
    }
}

<?php

namespace Laravel\Pulse\Handlers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\RedisAdapter;
use Symfony\Component\HttpFoundation\Response;

class HandleHttpRequest
{
    /**
     * Handle the completion of an HTTP request.
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        if (app(Pulse::class)->doNotReportUsage) {
            return;
        }

        RedisAdapter::xadd('pulse_requests', [
            // 'started_at' => $startedAt->toIso8601String(),
            'duration' => $startedAt->diffInMilliseconds(now()),
            // 'method' => $request->method(),
            // 'route' => $request->route()?->uri() ?? $request->path(),
            //'status' => $response->getStatusCode(),
            'route' => $request->method().' '.Str::start(($request->route()?->uri() ?? $request->path()), '/'),
            'user_id' => $request->user()?->id,
        ]);

        // TODO: Trim the stream to 7 days just in case...

        return;

        $duration = $startedAt->diffInMilliseconds(now());
        $route = $request->method().' '.($request->route()?->uri() ?? $request->path());

        $keyDate = $startedAt->format('Y-m-d');
        $keyExpiry = $startedAt->toImmutable()->startOfDay()->addDays(7)->timestamp;

        // Slow endpoint
        if ($duration >= config('pulse.slow_endpoint_threshold')) {
            $countKey = "pulse_slow_endpoint_request_counts:{$keyDate}";
            RedisAdapter::zincrby($countKey, 1, $route);
            RedisAdapter::expireat($countKey, $keyExpiry, 'NX');

            $durationKey = "pulse_slow_endpoint_total_durations:{$keyDate}";
            RedisAdapter::zincrby($durationKey, $duration, $route);
            RedisAdapter::expireat($durationKey, $keyExpiry, 'NX');

            $slowestKey = "pulse_slow_endpoint_slowest_durations:{$keyDate}";
            RedisAdapter::zadd($slowestKey, $duration, $route, 'GT');
            RedisAdapter::expireat($slowestKey, $keyExpiry, 'NX');

            if ($request->user()) {
                $userKey = "pulse_slow_endpoint_user_counts:{$keyDate}";
                RedisAdapter::zincrby($userKey, 1, $request->user()->id);
                RedisAdapter::expireat($userKey, $keyExpiry, 'NX');
            }
        }

        if (app(Pulse::class)->doNotReportUsage) {
            return;
        }

        // Top 10 users hitting the application
        if ($request->user()) {
            $hitsKey = "pulse_user_request_counts:{$keyDate}";
            RedisAdapter::zincrby($hitsKey, 1, $request->user()->id);
            RedisAdapter::expireAt($hitsKey, $keyExpiry, 'NX');
        }
    }
}

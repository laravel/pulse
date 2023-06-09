<?php

namespace Laravel\Pulse\Handlers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Redis;
use Symfony\Component\HttpFoundation\Response;

class HandleHttpRequest
{
    /**
     * Create a handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Redis $redis,
    ) {
        //
    }

    /**
     * Handle the completion of an HTTP request.
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        if ($this->pulse->doNotReportUsage) {
            return;
        }

        $id = $this->redis->xadd('pulse_requests', [
            // 'started_at' => $startedAt->toIso8601String(),
            'duration' => $startedAt->diffInMilliseconds(now()),
            // 'method' => $request->method(),
            // 'route' => $request->route()?->uri() ?? $request->path(),
            //'status' => $response->getStatusCode(),
            'route' => $request->method().' '.Str::start(($request->route()?->uri() ?? $request->path()), '/'),
            'user_id' => $request->user()?->id,
        ]);

        dump("id: {$id}");

        $oldestId = CarbonImmutable::createFromTimestampMs(Str::before($id, '-'))->subDays(7)->getTimestampMs();
        dump("oldest id: {$oldestId}");

        dump('trimming', );
        dump($this->redis->xtrim('pulse_requests', 'MINID', $oldestId));

        return;

        $duration = $startedAt->diffInMilliseconds(now());
        $route = $request->method().' '.($request->route()?->uri() ?? $request->path());

        $keyDate = $startedAt->format('Y-m-d');
        $keyExpiry = $startedAt->toImmutable()->startOfDay()->addDays(7)->timestamp;

        // Slow endpoint
        if ($duration >= config('pulse.slow_endpoint_threshold')) {
            $countKey = "pulse_slow_endpoint_request_counts:{$keyDate}";
            $this->redis->zincrby($countKey, 1, $route);
            $this->redis->expireat($countKey, $keyExpiry, 'NX');

            $durationKey = "pulse_slow_endpoint_total_durations:{$keyDate}";
            $this->redis->zincrby($durationKey, $duration, $route);
            $this->redis->expireat($durationKey, $keyExpiry, 'NX');

            $slowestKey = "pulse_slow_endpoint_slowest_durations:{$keyDate}";
            $this->redis->zadd($slowestKey, $duration, $route, 'GT');
            $this->redis->expireat($slowestKey, $keyExpiry, 'NX');

            if ($request->user()) {
                $userKey = "pulse_slow_endpoint_user_counts:{$keyDate}";
                $this->redis->zincrby($userKey, 1, $request->user()->id);
                $this->redis->expireat($userKey, $keyExpiry, 'NX');
            }
        }

        if ($this->pulse->doNotReportUsage) {
            return;
        }

        // Top 10 users hitting the application
        if ($request->user()) {
            $hitsKey = "pulse_user_request_counts:{$keyDate}";
            $this->redis->zincrby($hitsKey, 1, $request->user()->id);
            $this->redis->expireAt($hitsKey, $keyExpiry, 'NX');
        }
    }
}

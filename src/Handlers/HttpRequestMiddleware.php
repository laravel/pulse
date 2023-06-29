<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;

class HttpRequestMiddleware
{
    /**
     * Create a middleware instance.
     */
    public function __construct(protected Pulse $pulse)
    {
        //
    }

    /**
     * Invoke the middleware.
     */
    public function __invoke(callable $handler)
    {
        return function ($request, $options) use ($handler) {
            $startedAt = new CarbonImmutable;

            return $handler($request, $options)->then(function ($response) use ($request, $startedAt) {
                rescue(fn () => $this->record($request, $startedAt, new CarbonImmutable), report: false);

                return $response;
            }, function ($exception) {
                rescue(fn () => $this->record($request, $startedAt, new CarbonImmutable), report: false);

                return new RejectedPromise($exception);
            });
        };
    }

    /**
     * Record the request information.
     */
    protected function record(RequestInterface $request, CarbonImmutable $startedAt, CarbonImmutable $endedAt): void
    {
        $this->pulse->record('pulse_outgoing_requests', [
            'uri' => $request->getMethod().' '.Str::before($request->getUri(), '?'),
            'date' => $startedAt->toDateTimeString(),
            'duration' => $startedAt->diffInMilliseconds($endedAt),
            'user_id' => Auth::id(),
        ]);
    }
}

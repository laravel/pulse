<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Facades\Pulse;
use Psr\Http\Message\RequestInterface;

class HandleOutgoingRequest
{
    /**
     * Invoke the middleware.
     *
     * @param  (callable(\Psr\Http\Message\RequestInterface, array<string, mixed>): PromiseInterface)  $handler
     * @return (callable(\Psr\Http\Message\RequestInterface, array<string, mixed>): PromiseInterface)
     */
    public function __invoke(callable $handler): callable
    {
        return function ($request, $options) use ($handler) {
            $startedAt = new CarbonImmutable;

            return $handler($request, $options)->then(function ($response) use ($request, $startedAt) {
                Pulse::rescue(fn () => $this->record($request, $startedAt, new CarbonImmutable));

                return $response;
            }, function ($exception) use ($request, $startedAt) {
                Pulse::rescue(fn () => $this->record($request, $startedAt, new CarbonImmutable));

                return new RejectedPromise($exception);
            });
        };
    }

    /**
     * Record the request information.
     */
    protected function record(RequestInterface $request, CarbonImmutable $startedAt, CarbonImmutable $endedAt): void
    {
        Pulse::record(new Entry('pulse_outgoing_requests', [
            'uri' => $request->getMethod().' '.Str::before($request->getUri(), '?'),
            'date' => $startedAt->toDateTimeString(),
            'duration' => $startedAt->diffInMilliseconds($endedAt),
            'user_id' => Auth::id(),
        ]));
    }
}

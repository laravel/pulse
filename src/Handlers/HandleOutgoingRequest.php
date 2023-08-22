<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HandleOutgoingRequest
{
    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Invoke the middleware.
     *
     * @param  (callable(\Psr\Http\Message\RequestInterface, array<string, mixed>): PromiseInterface)  $handler
     * @return (callable(\Psr\Http\Message\RequestInterface, array<string, mixed>): PromiseInterface)
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $startedAt = new CarbonImmutable;

            return $handler($request, $options)->then(function (ResponseInterface $response) use ($request, $startedAt) {
                $this->pulse->rescue(fn () => $this->record($request, $startedAt, new CarbonImmutable));

                return $response;
            }, function (Throwable $exception) use ($request, $startedAt) {
                $this->pulse->rescue(fn () => $this->record($request, $startedAt, new CarbonImmutable));

                return new RejectedPromise($exception);
            });
        };
    }

    /**
     * Record the request information.
     */
    protected function record(RequestInterface $request, CarbonImmutable $startedAt, CarbonImmutable $endedAt): void
    {
        $this->pulse->record(new Entry('pulse_outgoing_requests', [
            'uri' => $request->getMethod().' '.Str::before($request->getUri(), '?'),
            'date' => $startedAt->toDateTimeString(),
            'duration' => $startedAt->diffInMilliseconds($endedAt),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]));
    }
}

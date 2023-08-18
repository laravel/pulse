<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;

class HandleOutgoingRequest
{
    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected AuthManager $auth,
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
        return function ($request, $options) use ($handler) {
            $startedAt = new CarbonImmutable;

            return $handler($request, $options)->then(function ($response) use ($request, $startedAt) {
                $this->pulse->rescue(fn () => $this->record($request, $startedAt, new CarbonImmutable));

                return $response;
            }, function ($exception) use ($request, $startedAt) {
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
        $this->pulse->record(new Entry(Table::OutgoingRequest, [
            'uri' => $request->getMethod().' '.Str::before($request->getUri(), '?'),
            'date' => $startedAt->toDateTimeString(),
            'duration' => $startedAt->diffInMilliseconds($endedAt),
            'user_id' => $this->auth->hasUser()
                ? $this->auth->id()
                : fn () => $this->auth->id(),
        ]));
    }
}

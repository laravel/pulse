<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Str;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @internal
 */
class OutgoingRequests
{
    use Concerns\ConfiguresAfterResolving;

    /**
     * The table to record to.
     */
    public string $table = 'pulse_outgoing_requests';

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Register the recorder.
     */
    public function register(callable $record, Application $app): void
    {
        if (method_exists(HttpFactory::class, 'globalMiddleware')) {
            $this->afterResolving($app, HttpFactory::class, fn (HttpFactory $factory) => $factory->globalMiddleware($this->middleware($record)));
        }
    }

    /**
     * Record the outgoing request.
     */
    public function record(RequestInterface $request, CarbonImmutable $startedAt): Entry
    {
        $endedAt = new CarbonImmutable;

        return new Entry($this->table, [
            'uri' => $request->getMethod().' '.Str::before($request->getUri(), '?'),
            'date' => $startedAt->toDateTimeString(),
            'duration' => $startedAt->diffInMilliseconds($endedAt),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * The recorder's middleware.
     */
    protected function middleware(callable $record): callable
    {
        return fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $record) {
            $startedAt = new CarbonImmutable;

            return $handler($request, $options)->then(function (ResponseInterface $response) use ($request, $startedAt, $record) {
                $record($request, $startedAt);

                return $response;
            }, function (Throwable $exception) use ($request, $startedAt, $record) {
                $record($request, $startedAt);

                return new RejectedPromise($exception);
            });
        };
    }
}

<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @internal
 */
class SlowOutgoingRequests
{
    use Concerns\Ignores, Concerns\Sampling, Concerns\Groups, ConfiguresAfterResolving;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
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
    public function record(RequestInterface $request, int $startedAt): void
    {
        [$timestamp, $endedAt, $method, $uri] = with(CarbonImmutable::now(), fn ($now) => [
            $now->getTimestamp(),
            $now->getTimestampMs(),
            $request->getMethod(),
            $request->getUri(),
        ]);

        $this->pulse->lazy(function () use ($startedAt, $timestamp, $endedAt, $method, $uri) {
            if (
                ! $this->shouldSample() ||
                $this->shouldIgnore($uri) ||
                ($duration = $endedAt - $startedAt) < $this->config->get('pulse.recorders.'.self::class.'.threshold')
            ) {
                return;
            }

            $this->pulse->record(
                type: 'slow_outgoing_request',
                key: json_encode([$method, $this->group($uri)], flags: JSON_THROW_ON_ERROR),
                value: $duration,
                timestamp: $timestamp,
            )->max()->count();
        });
    }

    /**
     * The recorder's middleware.
     */
    protected function middleware(callable $record): callable
    {
        return fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $record) {
            $startedAt = CarbonImmutable::now()->getTimestampMs();

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

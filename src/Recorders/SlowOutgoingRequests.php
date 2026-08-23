<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Pulse;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @internal
 */
class SlowOutgoingRequests
{
    use Concerns\Groups,
        Concerns\Ignores,
        Concerns\Sampling,
        Concerns\Thresholds,
        ConfiguresAfterResolving;

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
        $this->afterResolving($app, Factory::class, fn (Factory $factory) => $factory->globalMiddleware($this->middleware($record)));
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
            static::normalizeUrl($request->getUri()),
        ]);

        $this->pulse->lazy(function () use ($startedAt, $timestamp, $endedAt, $method, $uri) {
            if (
                ! $this->shouldSample() ||
                $this->shouldIgnore($uri) ||
                $this->underThreshold($duration = $endedAt - $startedAt, $uri)
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

    /**
     * Normalize the given URL and mask user & password information.
     */
    public static function normalizeUrl(string $url): string
    {
        try {
            $uri = Uri::new($url);
        } catch (SyntaxError) {
            // Guzzle accepted this URI, but it cannot be parsed here. Strip any
            // user info conservatively rather than failing the request pipeline.
            return preg_replace('~://[^/?#]*@~', '://', $url);
        }

        if (is_null($uri->getUsername())) {
            return $url;
        }

        return $uri->withUserInfo('', null)->toString();
    }
}

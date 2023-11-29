<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Contracts\Groupable;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @internal
 */
class SlowOutgoingRequests implements Groupable
{
    use Concerns\Ignores, Concerns\Sampling, ConfiguresAfterResolving;

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
    public function record(RequestInterface $request, CarbonImmutable $startedAt): void
    {
        $endedAt = CarbonImmutable::now();

        if (! $this->shouldSample() || $this->shouldIgnore($request->getUri())) {
            return;
        }

        $duration = $startedAt->diffInMilliseconds($endedAt);

        if ($duration < $this->config->get('pulse.recorders.'.self::class.'.threshold')) {
            return;
        }

        $this->pulse->record(
            type: 'slow_outgoing_request',
            // this returns a stirng now
            key: $this->group(json_encode([$request->getMethod(), $request->getUri()])),
            value: $duration,
            timestamp: $startedAt,
        )->max()->count();
    }

    /**
     * Return a closure that groups the given value.
     */
    public function group(string $key): string
    {
        // TODO
        return 'TODO';

        return function () use ($key) {
            [$method, $uri] = json_decode($key);

            foreach ($this->config->get('pulse.recorders.'.self::class.'.groups') as $pattern => $replacement) {
                $group = preg_replace($pattern, $replacement, $uri, count: $count);

                if ($count > 0 && $group !== null) {
                    return json_encode([$method, $group]);
                }
            }

            return $key;
        };
    }

    /**
     * The recorder's middleware.
     */
    protected function middleware(callable $record): callable
    {
        return fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $record) {
            $startedAt = CarbonImmutable::now();

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

<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Closure;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Contracts\Grouping;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @internal
 */
class OutgoingRequests implements Grouping // TODO: Rename to SlowOutgoingRequests
{
    use Concerns\Ignores;
    use Concerns\Sampling;
    use ConfiguresAfterResolving;

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
    public function record(RequestInterface $request, CarbonImmutable $startedAt): ?Entry
    {
        $endedAt = new CarbonImmutable;

        if (! $this->shouldSample() || $this->shouldIgnore($request->getUri())) {
            return null;
        }

        $duration = $startedAt->diffInMilliseconds($endedAt);
        $slow = $duration >= $this->config->get('pulse.recorders.'.self::class.'.threshold');

        if ($slow) {
            return Entry::make(
                timestamp: (int) $startedAt->timestamp,
                type: 'slow_outgoing_request',
                key: $this->group($request->getMethod().' '.$request->getUri()),
                value: $duration
            )->count()->max();
        }

        return null;
    }

    /**
     * Return a closure that groups the given value.
     *
     * @return Closure(): string
     */
    public function group(string $value): Closure
    {
        [$method, $uri] = explode(' ', $value, 2);

        return function () use ($method, $uri) {
            foreach ($this->config->get('pulse.recorders.'.self::class.'.groups') as $pattern => $replacement) {
                $group = preg_replace($pattern, $replacement, $uri, count: $count);

                if ($count > 0 && $group !== null) {
                    return "{$method} {$group}";
                }
            }

            return "{$method} {$uri}";
        };
    }

    /**
     * Return the column that grouping should be applied to.
     */
    public function groupColumn(): string
    {
        return 'uri';
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

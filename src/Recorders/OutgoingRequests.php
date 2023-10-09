<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Closure;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
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
    public function record(RequestInterface $request, CarbonImmutable $startedAt): Entry
    {
        $endedAt = new CarbonImmutable;

        return new Entry($this->table, [
            'uri' => $this->normalizeUri($request),
            'date' => $startedAt->toDateTimeString(),
            'duration' => $startedAt->diffInMilliseconds($endedAt),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Normalize the request URI.
     */
    protected function normalizeUri(RequestInterface $request): Closure
    {
        $method = $request->getMethod();

        $uri = $request->getUri();

        return function () use ($method, $uri) {
            foreach ($this->config->get('pulse.outgoing_request_uri_map') as $pattern => $replacement) {
                $normalized = preg_replace($pattern, $replacement, $uri, count: $count);

                if ($count > 0 && $normalized !== null) {
                    return "{$method} {$normalized}";
                }
            }

            return "{$method} {$uri}";
        };
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

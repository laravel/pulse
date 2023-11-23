<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class Requests
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
        $this->afterResolving(
            $app,
            Kernel::class,
            fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record) // @phpstan-ignore method.notFound
        );
    }

    /**
     * Record the request.
     *
     * @return ?list<\Laravel\Pulse\Entry>
     */
    public function record(Carbon $startedAt, Request $request, Response $response): ?array
    {
        if (! ($route = $request->route()) instanceof Route) {
            return null;
        }

        $path = $route->getDomain().Str::start($route->uri(), '/');
        $via = null;

        if ($route->named('*livewire.update')) {
            $snapshot = json_decode($request->input('components.0.snapshot'));

            if (isset($snapshot->memo->path)) {
                $via = $path;
                $path = Str::start($snapshot->memo->path, '/');
            }
        }

        if (! $this->shouldSample() || $this->shouldIgnore($path)) {
            return null;
        }

        // TODO: Separate requests and slow requests so they can be sampled independently.

        $duration = $startedAt->diffInMilliseconds();
        $slow = $duration >= $this->config->get('pulse.recorders.'.self::class.'.threshold');

        $entries = [];

        // TODO: Fix for users that logout during the request.
        if (Auth::check()) {
            $entries[] = (new Entry(
                timestamp: (int) $startedAt->timestamp,
                type: 'user_request',
                key: $this->pulse->authenticatedUserIdResolver(),
            ))->count();
        }

        if ($slow) {
            $entries[] = (new Entry(
                timestamp: (int) $startedAt->timestamp,
                type: 'slow_request',
                key: $request->method().' '.$path.($via ? " ($via)" : ''),
                value: $duration
            ))->count()->max();

            // TODO: Fix for users that logout during the request.
            if (Auth::check()) {
                $entries[] = (new Entry(
                    timestamp: (int) $startedAt->timestamp,
                    type: 'slow_user_request',
                    key: $this->pulse->authenticatedUserIdResolver(),
                ))->count();
            }
        }

        if (count($entries) === 0) {
            return null;
        }

        return $entries;
    }
}

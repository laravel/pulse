<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class UserRequests
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
        $this->afterResolving(
            $app,
            Kernel::class,
            fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record) // @phpstan-ignore method.notFound
        );
    }

    /**
     * Record the request.
     */
    public function record(Carbon $startedAt, Request $request, Response $response): void
    {
        if (
            ! ($userId = $this->pulse->resolveAuthenticatedUserId()) ||
            ! ($route = $request->route()) instanceof Route ||
            ! $this->shouldSample()
        ) {
            return;
        }

        $path = $route->getDomain().Str::start($route->uri(), '/');

        if ($route->named('*livewire.update')) {
            $snapshot = json_decode($request->input('components.0.snapshot'));

            if (isset($snapshot->memo->path)) {
                $path = Str::start($snapshot->memo->path, '/');
            }
        }

        if ($this->shouldIgnore($path)) {
            return;
        }

        $this->pulse->record('user_request', $userId, timestamp: $startedAt->getTimestamp())->count();
    }
}

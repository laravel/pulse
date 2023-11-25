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
class SlowRequests
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
            ($duration = $startedAt->diffInMilliseconds()) < $this->config->get('pulse.recorders.'.self::class.'.threshold') ||
            ! ($route = $request->route()) instanceof Route ||
            ! $this->shouldSample()
        ) {
            return;
        }

        $path = $route->getDomain().Str::start($route->uri(), '/');
        $via = '';

        if ($route->named('*livewire.update')) {
            $snapshot = json_decode($request->input('components.0.snapshot'), flags: JSON_THROW_ON_ERROR);

            if (isset($snapshot->memo->path)) {
                $via = " ({$path})";
                $path = Str::start($snapshot->memo->path, '/');
            }
        }

        if ($this->shouldIgnore($path)) {
            return;
        }

        $this->pulse->record(
            type: 'slow_request',
            key: "{$request->method()} {$path}{$via}",
            value: $duration,
            timestamp: $startedAt,
        )->count()->max();

        if ($userId = $this->pulse->resolveAuthenticatedUserId()) {
            $this->pulse->record(
                type: 'slow_user_request',
                key: $userId,
                timestamp: $startedAt,
            )->count();
        }
    }
}

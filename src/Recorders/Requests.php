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
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class Requests
{
    use Concerns\Ignores;
    use ConfiguresAfterResolving;

    /**
     * The table to record to.
     */
    public string $table = 'pulse_requests';

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
        $this->afterResolving($app, Kernel::class, fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record));
    }

    /**
     * Record the request.
     */
    public function record(Carbon $startedAt, Request $request, Response $response): ?Entry
    {
        $path = Str::start($this->getPath($request), '/');

        if ($this->shouldIgnore($path) || $this->shouldIgnoreLivewireRequest($request)) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $startedAt->toDateTimeString(),
            'route' => $request->method().' '.$path,
            'duration' => $startedAt->diffInMilliseconds(),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Get the path from the request.
     */
    protected function getPath(Request $request): string
    {
        $route = $request->route();

        return $route instanceof Route ? $route->uri() : $request->path();
    }

    /**
     * Determine whether any Livewire component updates should be ignored.
     */
    protected function shouldIgnoreLivewireRequest(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route || ! $route->named('*livewire.update')) {
            return false;
        }

        return $request
            ->collect('components.*.snapshot')
            ->contains(fn ($snapshot) => $this->shouldIgnore(Str::start(json_decode($snapshot)->memo->path, '/')));
    }
}

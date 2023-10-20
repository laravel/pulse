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
    use Concerns\Sampling;
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
        $this->afterResolving($app, Kernel::class, fn (Kernel $kernel) => $kernel->whenRequestLifecycleIsLongerThan(-1, $record)); // @phpstan-ignore method.notFound
    }

    /**
     * Record the request.
     */
    public function record(Carbon $startedAt, Request $request, Response $response): ?Entry
    {
        if (! ($route = $request->route()) instanceof Route) {
            return null;
        }

        $path = Str::start($route->uri(), '/');

        if (! $this->shouldSample() || $this->shouldIgnore($path) || $this->shouldIgnoreLivewireRequest($request)) {
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
     * Determine whether any Livewire component updates should be ignored.
     */
    protected function shouldIgnoreLivewireRequest(Request $request): bool
    {
        if (! ($route = $request->route()) instanceof Route || ! $route->named('*livewire.update')) {
            return false;
        }

        return $request
            ->collect('components.*.snapshot')
            ->contains(fn ($snapshot) => $this->shouldIgnore(Str::start(json_decode($snapshot)->memo->path, '/')));
    }
}

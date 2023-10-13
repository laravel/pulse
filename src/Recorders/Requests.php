<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
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
        $route = Str::start(($request->route()?->uri() ?? $request->path()), '/');

        if ($this->shouldIgnoreRoute($route) || $this->shouldIgnoreLivewireComponent($request)) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $startedAt->toDateTimeString(),
            'route' => $request->method().' '.$route,
            'duration' => $startedAt->diffInMilliseconds(),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Should the given route be ignored.
     */
    protected function shouldIgnoreRoute($route): bool
    {
        $ignore = $this->config->get('pulse.recorders.'.static::class.'.ignore');

        foreach ($ignore as $pattern) {
            if (preg_match($pattern, $route) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Should any Livewire component updates in the route cause the request to be ignored.
     */
    protected function shouldIgnoreLivewireComponent($request): bool
    {
        $ignore = $this->config->get('pulse.recorders.'.static::class.'.ignore');

        if ($request->route()?->getName() === 'livewire.update') {
            $components = $request->collect('components.*.snapshot')->map(fn ($snapshot) => json_decode($snapshot))->pluck('memo.name');

            foreach ($components as $component) {
                foreach ($ignore as $pattern) {
                    if (preg_match($pattern, $component) === 1) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

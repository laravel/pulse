<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class HttpRequests
{
    /**
     * The table to record to.
     */
    public string $table = 'pulse_requests';

    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Register the recorder.
     */
    public function register(callable $record, Kernel $kernel): void
    {
        $kernel->whenRequestLifecycleIsLongerThan(-1, $record);
    }

    /**
     * Handle the completion of an HTTP request.
     */
    public function record(Carbon $startedAt, Request $request, Response $response): Entry
    {
        return new Entry($this->table, [
            'date' => $startedAt->toDateTimeString(),
            'route' => $request->method().' '.Str::start(($request->route()?->uri() ?? $request->path()), '/'),
            'duration' => $startedAt->diffInMilliseconds(),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }
}

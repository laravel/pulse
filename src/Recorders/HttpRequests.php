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
    /** @var list<string> */
    public array $tables = ['pulse_requests'];

    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    public function register(callable $record, Kernel $kernel): void
    {
        $kernel->whenRequestLifecycleIsLongerThan(-1, $record);
    }

    /**
     * Handle the completion of an HTTP request.
     */
    public function record(Carbon $startedAt, Request $request, Response $response): Entry
    {
        return new Entry($this->tables[0], [
            'date' => $startedAt->toDateTimeString(),
            'route' => $request->method().' '.Str::start(($request->route()?->uri() ?? $request->path()), '/'),
            'duration' => $startedAt->diffInMilliseconds(),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }
}

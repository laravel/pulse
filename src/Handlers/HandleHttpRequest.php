<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

class HandleHttpRequest
{
    /**
     * Create a handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Handle the completion of an HTTP request.
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        rescue(function () use ($startedAt, $request) {
            $now = new CarbonImmutable();

            $this->pulse->record('pulse_requests', [
                'date' => $startedAt->toDateTimeString(),
                'user_id' => $request->user()?->id,
                'route' => $request->method().' '.Str::start(($request->route()?->uri() ?? $request->path()), '/'),
                'duration' => $startedAt->diffInMilliseconds($now),
            ]);
        }, report: false);
    }
}

<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

class HandleHttpRequest
{
    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected AuthManager $auth,
    ) {
        //
    }

    /**
     * Handle the completion of an HTTP request.
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        $this->pulse->rescue(function () use ($startedAt, $request) {
            $now = new CarbonImmutable();

            $this->pulse->record(new Entry('pulse_requests', [
                'date' => $startedAt->toDateTimeString(),
                'route' => $request->method().' '.Str::start(($request->route()?->uri() ?? $request->path()), '/'),
                'duration' => $startedAt->diffInMilliseconds(),
                'user_id' => $this->auth->hasUser()
                    ? $this->auth->id()
                    : fn () => $this->auth->id(),
            ]));
        });
    }
}

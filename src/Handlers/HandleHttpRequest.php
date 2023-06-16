<?php

namespace Laravel\Pulse\Handlers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Redis;
use Symfony\Component\HttpFoundation\Response;

class HandleHttpRequest
{
    /**
     * Create a handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Redis $redis,
    ) {
        //
    }

    /**
     * Handle the completion of an HTTP request.
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        if ($this->pulse->doNotReportUsage) {
            return;
        }

        DB::table('pulse_requests')->insert([
            'date' => $startedAt->toDateTimeString(),
            'user_id' => $request->user()?->id,
            'route' => $request->method().' '.Str::start(($request->route()?->uri() ?? $request->path()), '/'),
            'duration' => $startedAt->diffInMilliseconds(now()),
        ]);

        // Lottery::odds(1, 100)->winner(fn () =>
        //     DB::table('pulse_requests')->where('date', '<', now()->subDays(7)->toDateTimeString())->delete()
        // )->choose();
    }
}

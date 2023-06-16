<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Laravel\Pulse\Pulse;

class HandleQuery
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
     * Handle the execution of a database query.
     */
    public function __invoke(QueryExecuted $event): void
    {
        if ($this->pulse->doNotReportUsage) {
            return;
        }

        if ($event->time < config('pulse.slow_query_threshold')) {
            return;
        }

        DB::table('pulse_queries')->insert([
            'date' => now()->subMilliseconds(round($event->time))->toDateTimeString(),
            'user_id' => Auth::id(),
            'sql' => $event->sql,
            'duration' => round($event->time),
        ]);

        // Lottery::odds(1, 100)->winner(fn () =>
        //     DB::table('pulse_queries')->where('date', '<', now()->subDays(7)->toDateTimeString())->delete()
        // )->choose();
    }
}

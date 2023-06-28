<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
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
        rescue(function () use ($event) {
            $now = new DateTimeImmutable();

            if ($event->time < config('pulse.slow_query_threshold')) {
                return;
            }

            $this->pulse->record('pulse_queries', [
                'date' => $now->subMilliseconds(round($event->time))->toDateTimeString(),
                'user_id' => Auth::id(),
                'sql' => $event->sql,
                'duration' => round($event->time),
            ]);
        }, report: false);
    }
}

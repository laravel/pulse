<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Facades\Pulse;

class HandleQuery
{
    /**
     * Handle the execution of a database query.
     */
    public function __invoke(QueryExecuted $event): void
    {
        rescue(function () use ($event) {
            $now = new CarbonImmutable();

            if ($event->time < config('pulse.slow_query_threshold')) {
                return;
            }

            Pulse::record(new Entry('pulse_queries', [
                'date' => $now->subMilliseconds(round($event->time))->toDateTimeString(),
                'user_id' => Auth::id(),
                'sql' => $event->sql,
                'duration' => round($event->time),
            ]));
        }, report: false);
    }
}

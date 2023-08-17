<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;
use Laravel\Pulse\Facades\Pulse;

class HandleQuery
{
    /**
     * Handle the execution of a database query.
     */
    public function __invoke(QueryExecuted $event): void
    {
        Pulse::rescue(function () use ($event) {
            $now = new CarbonImmutable();

            if ($event->time < Config::get('pulse.slow_query_threshold')) {
                return;
            }

            Pulse::record(new Entry(Table::Query, [
                'date' => $now->subMilliseconds((int) $event->time)->toDateTimeString(),
                'sql' => $event->sql,
                'duration' => (int) $event->time,
                'user_id' => Auth::hasUser() ? Auth::id() : fn () => Auth::id(),
            ]));
        });
    }
}

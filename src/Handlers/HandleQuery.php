<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;

class HandleQuery
{
    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
        protected AuthManager $auth,
    ) {
        //
    }

    /**
     * Handle the execution of a database query.
     */
    public function __invoke(QueryExecuted $event): void
    {
        $this->pulse->rescue(function () use ($event) {
            $now = new CarbonImmutable();

            if ($event->time < $this->config->get('pulse.slow_query_threshold')) {
                return;
            }

            $this->pulse->record(new Entry('pulse_queries', [
                'date' => $now->subMilliseconds((int) $event->time)->toDateTimeString(),
                'sql' => $event->sql,
                'duration' => (int) $event->time,
                'user_id' => $this->auth->hasUser()
                    ? $this->auth->id()
                    : fn () => $this->auth->id(),
            ]));
        });
    }
}

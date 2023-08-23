<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class SlowQueries
{
    /** @var list<string> */
    public $tables = ['pulse_queries'];

    /** @var list<class-string> */
    public $events = [QueryExecuted::class];

    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Handle the execution of a database query.
     */
    public function record(QueryExecuted $event): ?Entry
    {
        $now = new CarbonImmutable();

        if ($event->time < $this->config->get('pulse.slow_query_threshold')) {
            return null;
        }

        return new Entry('pulse_queries', [
            'date' => $now->subMilliseconds((int) $event->time)->toDateTimeString(),
            'sql' => $event->sql,
            'duration' => (int) $event->time,
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }
}

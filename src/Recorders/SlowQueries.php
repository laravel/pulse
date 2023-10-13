<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class SlowQueries
{
    /**
     * The table to record to.
     */
    public string $table = 'pulse_slow_queries';

    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = QueryExecuted::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Record a slow query.
     */
    public function record(QueryExecuted $event): ?Entry
    {
        $now = new CarbonImmutable();

        if ($event->time < $this->config->get('pulse.recorders.'.static::class.'.threshold')) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $now->subMilliseconds((int) $event->time)->toDateTimeString(),
            'sql' => $event->sql,
            'duration' => (int) $event->time,
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }
}

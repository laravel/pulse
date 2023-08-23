<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobQueued as JobQueuedEvent;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class JobQueued
{
    public array $tables = ['pulse_jobs'];

    public array $events = [JobQueuedEvent::class];

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
    public function record(JobQueuedEvent $event): Entry
    {
        $now = new CarbonImmutable();

        return new Entry($this->tables[0], [
            'date' => $now->toDateTimeString(),
            'job' => is_string($event->job)
                ? $event->job
                : $event->job::class,
            'job_id' => $event->id,
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }
}

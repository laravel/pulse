<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;

class HandleQueuedJob
{
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
    public function __invoke(JobQueued $event): void
    {
        $this->pulse->rescue(function () use ($event) {
            $now = new CarbonImmutable();

            $this->pulse->record(new Entry('pulse_jobs', [
                'date' => $now->toDateTimeString(),
                'job' => is_string($event->job)
                    ? $event->job
                    : $event->job::class,
                'job_id' => $event->id,
                'user_id' => $this->pulse->authenticatedUserIdResolver(),
            ]));
        });
    }
}

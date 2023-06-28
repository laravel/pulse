<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Auth;
use Laravel\Pulse\Pulse;

class HandleQueuedJob
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
    public function __invoke(JobQueued $event): void
    {
        rescue(function () use ($event) {
            $now = new CarbonImmutable();

            $this->pulse->record('pulse_jobs', [
                'date' => $now->toDateTimeString(),
                'user_id' => Auth::id(),
                'job' => is_string($event->job)
                    ? $event->job
                    : $event->job::class,
                'job_id' => $event->id,
            ]);
        }, report: false);
    }
}

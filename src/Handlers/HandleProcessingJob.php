<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Queue\Events\JobProcessing;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Updates\RecordJobStart;

class HandleProcessingJob
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
    public function __invoke(JobProcessing $event): void
    {
        $now = now();

        if (! $this->pulse->shouldRecord) {
            return;
        }

        $this->pulse->recordUpdate(new RecordJobStart(
            $event->job->getJobId(),
            $now->toDateTimeString('millisecond')
        ));
    }
}

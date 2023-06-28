<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
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
        rescue(function () use ($event) {
            $now = new CarbonImmutable();

            $this->pulse->recordUpdate(new RecordJobStart(
                (string) $event->job->getJobId(),
                $now->toDateTimeString('millisecond')
            ));
        }, report: false);
    }
}

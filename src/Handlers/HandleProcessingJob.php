<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessing;
use Laravel\Pulse\Entries\JobStarted;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class HandleProcessingJob
{
    /**
     * Create a new handler instance.
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
        $this->pulse->rescue(function () use ($event) {
            $now = new CarbonImmutable();

            $this->pulse->record(new JobStarted(
                (string) $event->job->getJobId(),
                $now->toDateTimeString('millisecond')
            ));
        });
    }
}

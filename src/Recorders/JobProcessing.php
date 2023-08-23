<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessing as JobProcessingEvent;
use Laravel\Pulse\Entries\JobStarted;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class JobProcessing
{
    public array $tables = ['pulse_jobs'];

    public array $events = [JobProcessingEvent::class];

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
    public function record(JobProcessingEvent $event): Update
    {
        $now = new CarbonImmutable();

        return new JobStarted(
            (string) $event->job->getJobId(),
            $now->toDateTimeString('millisecond')
        );
    }
}

<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessing;
use Laravel\Pulse\Entries\JobStarted;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class HandleProcessingJob
{
    public array $tables = ['pulse_jobs'];

    public array $events = [JobProcessing::class];

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
    public function record(JobProcessing $event): Update
    {
        $now = new CarbonImmutable();

        return new JobStarted(
            (string) $event->job->getJobId(),
            $now->toDateTimeString('millisecond')
        );
    }
}

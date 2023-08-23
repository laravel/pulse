<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Laravel\Pulse\Entries\JobFinished;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class JobCompletion
{
    public array $tables = ['pulse_jobs'];

    public array $events = [JobFailed::class, JobProcessed::class];

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
    public function record(JobProcessed|JobFailed $event): Update
    {
        $now = new CarbonImmutable();

        // TODO respect slow limit configuration? I don't think we should
        // here, and instead we should have our "clear data" command do this
        // for us.

        return new JobFinished(
            (string) $event->job->getJobId(),
            $now->toDateTimeString('millisecond')
        );
    }
}

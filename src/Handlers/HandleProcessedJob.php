<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Laravel\Pulse\Entries\JobFinished;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class HandleProcessedJob
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
    public function __invoke(JobProcessed|JobFailed $event): void
    {
        $this->pulse->rescue(function () use ($event) {
            $now = new CarbonImmutable();

            // TODO respect slow limit configuration? I don't think we should
            // here, and instead we should have our "clear data" command do this
            // for us.

            $this->pulse->record(new JobFinished(
                (string) $event->job->getJobId(),
                $now->toDateTimeString('millisecond')
            ));
        });
    }
}

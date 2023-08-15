<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Laravel\Pulse\Entries\JobFinished;
use Laravel\Pulse\Facades\Pulse;

class HandleProcessedJob
{
    /**
     * Handle the execution of a database query.
     */
    public function __invoke(JobProcessed|JobFailed $event): void
    {
        Pulse::rescue(function () use ($event) {
            $now = new CarbonImmutable();

            // TODO respect slow limit configuration? I don't think we should
            // here, and instead we should have our "clear data" command do this
            // for us.

            Pulse::recordUpdate(new JobFinished(
                (string) $event->job->getJobId(),
                $now->toDateTimeString('millisecond')
            ));
        });
    }
}

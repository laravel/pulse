<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessed;
use Laravel\Pulse\Entries\JobFinished;
use Laravel\Pulse\Facades\Pulse;

class HandleProcessedJob
{
    /**
     * Handle the execution of a database query.
     */
    public function __invoke(JobProcessed $event): void
    {
        rescue(function () use ($event) {
            $now = new CarbonImmutable();

            // TODO respect slow limit configuration?

            Pulse::recordUpdate(new JobFinished(
                (string) $event->job->getJobId(),
                $now->toDateTimeString('millisecond')
            ));
        }, report: false);
    }
}

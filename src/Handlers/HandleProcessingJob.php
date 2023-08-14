<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessing;
use Laravel\Pulse\Entries\JobStarted;
use Laravel\Pulse\Facades\Pulse;

class HandleProcessingJob
{
    /**
     * Handle the execution of a database query.
     */
    public function __invoke(JobProcessing $event): void
    {
        Pulse::rescue(function () use ($event) {
            $now = new CarbonImmutable();

            Pulse::recordUpdate(new JobStarted(
                (string) $event->job->getJobId(),
                $now->toDateTimeString('millisecond')
            ));
        });
    }
}

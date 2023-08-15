<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Auth;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;
use Laravel\Pulse\Facades\Pulse;

class HandleQueuedJob
{
    /**
     * Handle the execution of a database query.
     */
    public function __invoke(JobQueued $event): void
    {
        Pulse::rescue(function () use ($event) {
            $now = new CarbonImmutable();

            Pulse::record(new Entry(Table::Job, [
                'date' => $now->toDateTimeString(),
                'user_id' => Auth::id(),
                'job' => is_string($event->job)
                    ? $event->job
                    : $event->job::class,
                'job_id' => $event->id,
            ]));
        });
    }
}

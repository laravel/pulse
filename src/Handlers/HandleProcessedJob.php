<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Pulse;

class HandleProcessedJob
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
    public function __invoke(JobProcessed $event): void
    {
        if ($this->pulse->doNotReportUsage) {
            return;
        }

        // TODO: this should capture "now()", but using a random duration to improve
        // the randomness without having to have long running jobs.
        $now = now()->addMilliseconds(rand(100, 10000));

        // TODO respect slow limit configuration

        DB::table('pulse_jobs')
            ->where('job_id', (string) $event->job->getJobId())
            ->update([
                'duration' => DB::raw('TIMESTAMPDIFF(MICROSECOND, `processing_started_at`, "'.$now->toDateTimeString('millisecond').'") / 1000'),
            ]);
    }
}

<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Laravel\Pulse\Pulse;

class HandleProcessingJob
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
    public function __invoke(JobProcessing $event): void
    {
        if ($this->pulse->doNotReportUsage) {
            return;
        }

        DB::table('pulse_jobs')
            ->where('job_id', (string) $event->job->getJobId())
            ->update([
                'processing_started_at' => now()->toDateTimeString('millisecond'),
            ]);

        // Lottery::odds(1, 100)->winner(fn () =>
        //     DB::table('pulse_jobs')->where('date', '<', now()->subDays(7)->toDateTimeString())->delete()
        // )->choose();
    }
}

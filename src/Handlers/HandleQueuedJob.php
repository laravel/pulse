<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Laravel\Pulse\Pulse;

class HandleQueuedJob
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
    public function __invoke(JobQueued $event): void
    {
        if ($this->pulse->doNotReportUsage) {
            return;
        }

        // TODO: handle the connection

        DB::table('pulse_jobs')->insert([
            'date' => now()->toDateTimeString(),
            'user_id' => Auth::id(),
            'job' => is_string($event->job)
                ? $event->job
                : $event->job::class,
            'job_id' => $event->id,
        ]);

        // Lottery::odds(1, 100)->winner(fn () =>
        //     DB::table('pulse_jobs')->where('date', '<', now()->subDays(7)->toDateTimeString())->delete()
        // )->choose();
    }
}

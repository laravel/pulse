<?php

namespace Laravel\Pulse\Updates;

use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Update;

class RecordJobDuration implements Update
{
    /**
     * Create a new update instance.
     */
    public function __construct(
        public string $jobId,
        public string $timestamp
    ) {
        //
    }

    /**
     * Perform the update.
     */
    public function perform(): void
    {
        DB::table('pulse_jobs')
            ->where('job_id', $this->jobId)
            ->update([
                'duration' => DB::raw('TIMESTAMPDIFF(MICROSECOND, `processing_started_at`, "'.$this->timestamp.'") / 1000'),
            ]);
    }
}

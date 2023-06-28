<?php

namespace Laravel\Pulse\Updates;

use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Update;

class RecordJobStart implements Update
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
                'processing_started_at' => $this->timestamp,
            ]);
    }
}

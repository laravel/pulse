<?php

namespace Laravel\Pulse\Updates;

use Illuminate\Support\Facades\DB;

class RecordJobStart
{
    /**
     * Create a new update instance.
     *
     * @param  string  $jobId
     * @param  string  $timestamp
     */
    public function __construct(
        public $jobId,
        public $timestamp
    ) {
        //
    }

    /**
     * Perform the update.
     */
    public function perform()
    {
        DB::table('pulse_jobs')
            ->where('job_id', $this->jobId)
            ->update([
                'processing_started_at' => $this->timestamp,
            ]);
    }
}

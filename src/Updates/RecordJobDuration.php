<?php

namespace Laravel\Pulse\Updates;

use Illuminate\Support\Facades\DB;

class RecordJobDuration
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
                'duration' => DB::raw('TIMESTAMPDIFF(MICROSECOND, `processing_started_at`, "'.$this->timestamp.'") / 1000'),
            ]);
    }
}

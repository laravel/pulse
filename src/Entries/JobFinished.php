<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Support\Facades\DB;

class JobFinished extends Update
{
    /**
     * Create a new update instance.
     */
    public function __construct(
        public string $jobId,
        public string $endedAt
    ) {
        //
    }

    /**
     * Perform the update.
     */
    public function perform(): void
    {
        DB::table($this->table())
            ->where('job_id', $this->jobId)
            ->update([
                'duration' => DB::raw('TIMESTAMPDIFF(MICROSECOND, `processing_started_at`, "'.$this->endedAt.'") / 1000'),
            ]);
    }

    /**
     * The update's table.
     */
    public function table(): string
    {
        return 'pulse_jobs';
    }
}

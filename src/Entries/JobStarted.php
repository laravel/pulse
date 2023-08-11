<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Database\Connection;

class JobStarted extends Update
{
    /**
     * Create a new JobStarted instance.
     */
    public function __construct(
        public string $jobId,
        public string $startedAt
    ) {
        //
    }

    /**
     * Perform the update.
     */
    public function perform(Connection $db): void
    {
        $db->table($this->table())
            ->where('job_id', $this->jobId)
            ->update([
                'processing_started_at' => $this->startedAt,
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

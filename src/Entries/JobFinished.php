<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class JobFinished extends Update
{
    /**
     * Create a new JobFinished instance.
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
    public function perform(Connection $db): void
    {
        $db->table($this->table()->value)
            ->where('job_id', $this->jobId)
            ->update([
                'duration' => $db->raw('TIMESTAMPDIFF(MICROSECOND, `processing_started_at`, "'.$this->endedAt.'") / 1000'),
            ]);
    }

    /**
     * The update's table.
     */
    public function table(): Table
    {
        return Table::Job;
    }
}

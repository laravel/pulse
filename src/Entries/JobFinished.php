<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Database\Connection;

/**
 * @internal
 */
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
        $db->table($this->table())
            ->where('job_id', $this->jobId)
            ->update([
                'duration' => $db->raw('TIMESTAMPDIFF(MICROSECOND, `processing_started_at`, "'.$this->endedAt.'") / 1000'),
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

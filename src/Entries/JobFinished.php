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
        public string $jobUuid,
        public string $startedProcessingAt,
        public int $duration,
    ) {
        //
    }

    /**
     * Perform the update.
     */
    public function perform(Connection $db): void
    {
        $db->table($this->table())
            ->where('job_uuid', $this->jobUuid)
            ->update([
                'duration' => $this->duration,
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

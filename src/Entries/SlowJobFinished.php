<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Database\Connection;

/**
 * @internal
 */
class SlowJobFinished extends Update
{
    /**
     * Create a new JobFinished instance.
     */
    public function __construct(
        public string $jobUuid,
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
                'slowest' => $db->raw("COALESCE(GREATEST(`slowest`,{$this->duration}),{$this->duration})"),
                'slow' => $db->raw('`slow` + 1'),
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

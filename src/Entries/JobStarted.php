<?php

namespace Laravel\Pulse\Entries;

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
    public function perform(): void
    {
        $this->query()
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

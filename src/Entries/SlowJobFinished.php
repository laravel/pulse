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
     * The update's table.
     */
    public function table(): string
    {
        return 'pulse_jobs';
    }
}

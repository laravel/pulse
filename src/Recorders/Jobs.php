<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class Jobs
{
    /**
     * The table to record to.
     */
    public string $table = 'pulse_jobs';

    /**
     * The time the last job started processing.
     */
    protected ?CarbonImmutable $lastJobStartedProcessingAt;

    /**
     * The events to listen for.
     *
     * @var list<class-string>
     */
    public array $listen = [
        JobReleasedAfterException::class,
        JobFailed::class,
        JobProcessed::class,
        JobProcessing::class,
        JobQueued::class,
    ];

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Record the job.
     */
    public function record(JobReleasedAfterException|JobFailed|JobProcessed|JobProcessing|JobQueued $event): Entry|Update|null
    {
        if ($event->connectionName === 'sync') {
            return null;
        }

        $now = new CarbonImmutable();

        if ($event instanceof JobQueued) {
            return new Entry($this->table, [
                'date' => $now->toDateTimeString(),
                'job' => is_string($event->job)
                    ? $event->job
                    : $event->job::class,
                'job_uuid' => $event->payload()['uuid'],
                'user_id' => $this->pulse->authenticatedUserIdResolver(),
            ]);
        }

        if ($event instanceof JobProcessing) {
            $this->lastJobStartedProcessingAt = $now;

            return null;
        }

        $duration = $this->lastJobStartedProcessingAt->diffInMilliseconds($now);

        if ($duration < $this->config->get('pulse.slow_job_threshold')) {
            return null;
        }

        return tap(new Update(
            $this->table,
            ['job_uuid' => (string) $event->job->uuid()],
            fn (array $attributes) => [
                'slowest' => max($attributes['slowest'] ?? 0, $duration),
                'slow' => $attributes['slow'] + 1,
            ],
        ), fn () => $this->lastJobStartedProcessingAt = null);
    }
}

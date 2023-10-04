<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Update;

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
                'connection' => $event->connectionName,
                'queue' => $event->job->queue ?? 'default',
                'user_id' => $this->pulse->authenticatedUserIdResolver(),
            ]);
        }

        // TODO: Store an entry per-retry?

        if ($event instanceof JobProcessing) {
            $this->lastJobStartedProcessingAt = $now;
            // TODO: Add update here?

            return null;
        }

        $duration = $this->lastJobStartedProcessingAt->diffInMilliseconds($now);
        $processingAt = $this->lastJobStartedProcessingAt?->toDateTimeString();
        $slow = $duration >= $this->config->get('pulse.slow_job_threshold') ? 1 : 0;

        if ($event instanceof JobReleasedAfterException) {
            return tap(new Update(
                $this->table,
                ['job_uuid' => (string) $event->job->uuid()],
                fn (array $attributes) => [
                    'processing_at' => $attributes['processing_at'] ?? $processingAt,
                    'slowest' => max($attributes['slowest'] ?? 0, $duration),
                    'slow' => $attributes['slow'] + $slow,
                ],
            ), fn () => $this->lastJobStartedProcessingAt = null);
        }

        if ($event instanceof JobProcessed) {
            return tap(new Update(
                $this->table,
                ['job_uuid' => (string) $event->job->uuid()],
                fn (array $attributes) => [
                    'processing_at' => $attributes['processing_at'] ?? $processingAt,
                    'processed_at' => $now->toDateTimeString(),
                    'slowest' => max($attributes['slowest'] ?? 0, $duration),
                    'slow' => $attributes['slow'] + $slow,
                ],
            ), fn () => $this->lastJobStartedProcessingAt = null);
        }

        if ($event instanceof JobFailed) {
            return tap(new Update(
                $this->table,
                ['job_uuid' => (string) $event->job->uuid()],
                fn (array $attributes) => [
                    'processing_at' => $attributes['processing_at'] ?? $processingAt,
                    'failed_at' => $now->toDateTimeString(),
                    'slowest' => max($attributes['slowest'] ?? 0, $duration),
                    'slow' => $attributes['slow'] + $slow,
                ],
            ), fn () => $this->lastJobStartedProcessingAt = null);
        }
    }
}

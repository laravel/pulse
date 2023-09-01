<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\SlowJobFinished;
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
        JobFailed::class,
        JobProcessed::class,
        JobProcessing::class,
        JobExceptionOccurred::class,
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
    public function record(JobExceptionOccurred|JobFailed|JobProcessed|JobProcessing|JobQueued $event): Entry|Update|null
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

        return tap(new SlowJobFinished(
            (string) $event->job->uuid(),
            $duration,
        ), fn () => $this->lastJobStartedProcessingAt = null);
    }
}

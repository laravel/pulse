<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Auth;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class Jobs
{
    use Concerns\Ignores;
    use Concerns\Sampling;

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
     *
     * @return \Laravel\Pulse\Entry|list<\Laravel\Pulse\Entry>|null
     */
    public function record(JobReleasedAfterException|JobFailed|JobProcessed|JobProcessing|JobQueued $event): Entry|array|null
    {
        if ($event->connectionName === 'sync') {
            return null;
        }

        $now = new CarbonImmutable();

        if ($event instanceof JobQueued) {
            if (
                ! $this->shouldSampleDeterministically($event->payload()['uuid'])
                    || $this->shouldIgnore(is_string($event->job) ? $event->job : $event->job::class)
            ) {
                return null;
            }

            return array_values(array_filter([
                (new Entry(
                    timestamp: (int) $now->timestamp,
                    type: 'queued', // TODO: prefix with 'queued:' or something?
                    key: $event->connectionName.':'.($event->job->queue ?? 'default')
                ))->count(),
                // TODO: Make this better.
                Auth::check() ? (new Entry(
                    timestamp: (int) $now->timestamp,
                    type: 'user_job', // TODO: prefix with 'queued:' or 'usage'?
                    key: $this->pulse->authenticatedUserIdResolver()
                ))->count() : null,
            ]));
        }

        if (! $this->shouldSampleDeterministically((string) $event->job->uuid()) ||
            $this->shouldIgnore($event->job->resolveName())) {

            return null;
        }

        if ($event instanceof JobProcessing) {
            $this->lastJobStartedProcessingAt = $now;

            return (new Entry(
                timestamp: (int) $now->timestamp,
                type: 'processing',
                key: $event->job->getConnectionName().':'.$event->job->getQueue()
            ))->count();
        }

        if ($this->lastJobStartedProcessingAt === null) {
            return null;
        }

        $duration = $this->lastJobStartedProcessingAt->diffInMilliseconds($now);
        $slow = $duration >= $this->config->get('pulse.recorders.'.self::class.'.threshold');

        return array_values(array_filter([
            (new Entry(
                timestamp: (int) $now->timestamp,
                type: match (true) {
                    $event instanceof JobReleasedAfterException => 'released',
                    $event instanceof JobFailed => 'failed',
                    $event instanceof JobProcessed => 'processed',
                },
                key: $event->job->getConnectionName().':'.$event->job->getQueue(),
            ))->count(),
            $slow ? (new Entry(
                timestamp: (int) $now->timestamp,
                type: 'slow_job',
                key: $event->job->resolveName(),
                value: $duration,
            ))->count()->max() : null,
        ]));
    }
}

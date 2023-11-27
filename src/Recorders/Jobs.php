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
     */
    public function record(JobReleasedAfterException|JobFailed|JobProcessed|JobProcessing|JobQueued $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        $now = new CarbonImmutable();

        [$uuid, $name] = match (get_class($event)) {
            JobQueued::class => [
                $event->payload()['uuid'],
                match (true) {
                    is_string($event->job) => $event->job,
                    method_exists($event->job, 'displayName') => $event->job->displayName(),
                    default => $event->job::class,
                },
            ],
            default => [$event->job->uuid(), $event->job->resolveName()],
        };

        if (! $this->shouldSampleDeterministically($uuid) || $this->shouldIgnore($name)) {
            return;
        }

        // Queue Stats

        $this->pulse->record(
            type: match (get_class($event)) { // TODO: Just record the event class name?
                JobQueued::class => 'queued',
                JobProcessing::class => 'processing',
                JobProcessed::class => 'processed',
                JobReleasedAfterException::class => 'released',
                JobFailed::class => 'failed',
            },
            key: match (get_class($event)) {
                JobQueued::class => $event->connectionName.':'.($event->job->queue ?? 'default'),
                default => $event->job->getConnectionName().':'.$event->job->getQueue(),
            },
            timestamp: $now,
        )->sum()->bucketOnly();

        // Slow Jobs
        // TODO: Separate recorder so it can be sampled differently?

        if ($event instanceof JobProcessing) {
            $this->lastJobStartedProcessingAt = $now;
        } elseif (! $event instanceof JobQueued && isset($this->lastJobStartedProcessingAt) && $this->lastJobStartedProcessingAt !== null) {
            $duration = $this->lastJobStartedProcessingAt->diffInMilliseconds($now);
            $this->lastJobStartedProcessingAt = null;

            if ($duration >= $this->config->get('pulse.recorders.'.self::class.'.threshold')) {
                $this->pulse->record('slow_job', $name, $duration, timestamp: $now)->max();
            }
        }

        // User dispatching Jobs
        // TODO: Separate recorder so it can be sampled differently?

        if ($event instanceof JobQueued && Auth::check()) {
            $this->pulse->record('user_job', $this->pulse->authenticatedUserIdResolver(), timestamp: $now)->sum();
        }
    }
}

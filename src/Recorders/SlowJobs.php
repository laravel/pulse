<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class SlowJobs
{
    use Concerns\Ignores, Concerns\Sampling;

    /**
     * The time the last job started processing.
     */
    protected ?int $lastJobStartedProcessingAt = null;

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
    public function record(JobReleasedAfterException|JobFailed|JobProcessed|JobProcessing $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        [$timestamp, $timestampMs] = with(CarbonImmutable::now(), fn ($now) => [
            $now->getTimestamp(),
            $now->getTimestampMs(),
        ]);

        if ($event instanceof JobProcessing) {
            $this->lastJobStartedProcessingAt = $timestampMs;

            return;
        }

        $name = $event->job->resolveName();
        $lastJobStartedProcessingAt = $this->lastJobStartedProcessingAt;
        $this->lastJobStartedProcessingAt = null;

        $this->pulse->lazy(function () use ($timestamp, $timestampMs, $name, $lastJobStartedProcessingAt) {
            if (! $this->shouldSample() || $this->shouldIgnore($name) || $lastJobStartedProcessingAt === null) {
                return;
            }

            $duration = $timestampMs - $lastJobStartedProcessingAt;

            if ($duration >= $this->config->get('pulse.recorders.'.self::class.'.threshold')) {
                $this->pulse->record(
                    'slow_job',
                    $name,
                    $duration,
                    timestamp: $timestamp,
                )->max()->count();
            }
        });
    }
}

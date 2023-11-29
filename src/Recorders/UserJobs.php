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
class UserJobs
{
    use Concerns\Ignores, Concerns\Sampling;

    /**
     * The events to listen for.
     *
     * @var list<class-string>
     */
    public array $listen = [
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
    public function record(JobQueued $event): void
    {
        if ($event->connectionName === 'sync') {
            return;
        }

        $timestamp = CarbonImmutable::now()->getTimestamp();
        $name = match (true) {
            is_string($event->job) => $event->job,
            method_exists($event->job, 'displayName') => $event->job->displayName(),
            default => $event->job::class,
        };
        $userIdResolver = $this->pulse->authenticatedUserIdResolver();

        $this->pulse->lazy(function () use ($timestamp, $name, $userIdResolver) {
            if (! $this->shouldSample() || $this->shouldIgnore($name) || ($userId = $userIdResolver()) === null) {
                return;
            }

            $this->pulse->record(
                type: 'user_job',
                key: (string) $userId,
                timestamp: $timestamp,
            )->count();
        });
    }
}

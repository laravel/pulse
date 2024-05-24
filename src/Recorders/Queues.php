<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Pulse\Pulse;
use ReflectionClass;

/**
 * @internal
 */
class Queues
{
    use Concerns\Ignores, Concerns\Sampling;

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

        [$timestamp, $class, $connection, $queue, $uuid, $name] = [
            CarbonImmutable::now()->getTimestamp(),
            $class = $event::class,
            match ($class) {
                JobQueued::class => $event->connectionName,
                default => $event->job->getConnectionName(), // @phpstan-ignore method.nonObject
            },
            $this->resolveQueue($event),
            match ($class) {
                JobQueued::class => $event->payload()['uuid'], // @phpstan-ignore method.notFound
                default => $event->job->uuid(), // @phpstan-ignore method.nonObject
            },
            match ($class) {
                JobQueued::class => match (true) {
                    is_string($event->job) => $event->job,
                    method_exists($event->job, 'displayName') => $event->job->displayName(),
                    default => $event->job::class,
                },
                default => $event->job->resolveName(), // @phpstan-ignore method.nonObject
            },
        ];

        $this->pulse->lazy(function () use ($timestamp, $class, $connection, $queue, $uuid, $name) {
            if (! $this->shouldSampleDeterministically($uuid) || $this->shouldIgnore($name)) {
                return;
            }

            $queue = $queue === null
                ? $this->getDefaultQueue($connection)
                : $this->normalizeSqsQueue($connection, $queue);

            $this->pulse->record(
                type: match ($class) { // @phpstan-ignore match.unhandled
                    JobQueued::class => 'queued',
                    JobProcessing::class => 'processing',
                    JobProcessed::class => 'processed',
                    JobReleasedAfterException::class => 'released',
                    JobFailed::class => 'failed',
                },
                key: "{$connection}:{$queue}",
                timestamp: $timestamp,
            )->count()->onlyBuckets();
        });
    }

    /**
     * Get the default queue for the connection
     */
    protected function getDefaultQueue(string $connection): string
    {
        return $this->config->get('queue.connections.'.$connection.'.queue', 'default');
    }

    /**
     * Normalize the SQS queue name.
     */
    protected function normalizeSqsQueue(string $connection, string $queue): string
    {
        $config = $this->config->get("queue.connections.{$connection}") ?? [];

        if (($config['driver'] ?? null) !== 'sqs') {
            return $queue;
        }

        if ($config['prefix'] ?? null) {
            $prefix = preg_quote($config['prefix'], '#');

            $queue = preg_replace("#^{$prefix}/#", '', $queue) ?? $queue;
        }

        if ($config['suffix'] ?? null) {
            $suffix = preg_quote($config['suffix'], '#');

            $queue = preg_replace("#{$suffix}$#", '', $queue) ?? $queue;
        }

        return $queue;
    }

    /**
     * Resolve the queue.
     */
    protected function resolveQueue(JobReleasedAfterException|JobFailed|JobProcessed|JobProcessing|JobQueued $event): ?string
    {
        return match ($event::class) {
            JobQueued::class => match (is_object($event->job) ? $event->job::class : $event->job) {
                CallQueuedListener::class => $this->resolveQueuedListenerQueue($event),
                default => $event->job->queue ?? null,
            },
            default => $event->job->getQueue(), // @phpstan-ignore method.nonObject
        };
    }

    /**
     * Resolve the queued listener's queue.
     */
    protected function resolveQueuedListenerQueue(JobQueued $event): ?string
    {
        return with(
            (new ReflectionClass($event->job->class))->newInstanceWithoutConstructor(), // @phpstan-ignore property.nonObject
            fn ($listener) => method_exists($listener, 'viaQueue')
                ? $listener->viaQueue($event->job->data[0] ?? null)
                : ($listener->queue ?? null)
        );
    }
}

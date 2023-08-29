<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Events\Beat;

/**
 * @internal
 */
class QueueSizes
{
    /**
     * The table to record to.
     */
    public string $table = 'pulse_queue_sizes';

    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = Beat::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Repository $config,
        protected QueueManager $queue,
        protected CacheManager $cache,
        protected FailedJobProviderInterface $failedJobs,
    ) {
        //
    }

    /**
     * Record the queue sizes.
     *
     * @return \Illuminate\Support\Enumerable<int, \Laravel\Pulse\Entries\Entry>
     */
    public function record(Beat $event): Enumerable
    {
        if ($event->time->second % 15 !== 0) {
            return collect([]);
        }

        if (! $this->cache->lock("laravel:pulse:check-queue-size:{$event->time->timestamp}", (int) $event->interval->totalSeconds)->get()) {
            return collect([]);
        }

        return collect($this->config->get('pulse.queues'))
            ->groupBy(fn ($value, $key) => is_int($key) ? $this->config->get('queue.default') : $key)
            ->map->flatten()
            ->flatMap(fn (Collection $queues, string $connection) => $queues->map(fn (string $queue) => new Entry($this->table, [
                'date' => $event->time->toDateTimeString(),
                'connection' => $connection,
                'queue' => $queue,
                'size' => $this->queue->connection($connection)->size($queue),
                'failed' => $this->failedJobs instanceof CountableFailedJobProvider
                    ? $this->failedJobs->count($connection, $queue)
                    : 0,
            ])));
    }
}

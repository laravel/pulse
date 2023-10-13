<?php

namespace Laravel\Pulse\Queries;

use Illuminate\Config\Repository;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

/**
 * @internal
 */
class Queues
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Repository $config,
        protected QueueManager $queue,
        protected FailedJobProviderInterface $failedJobs,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Enumerable<int, array{connection: string, queue: string, size: int, failed: int}>
     */
    public function __invoke(): Enumerable
    {
        // TODO: Get historic and current stats from the pulse_jobs table, similar to system stats charts and current value.
        return collect($this->config->get('pulse.queues'))
            ->groupBy(fn ($value, $key) => is_int($key) ? $this->config->get('queue.default') : $key)
            ->map->flatten()
            ->flatMap(fn (Collection $queues, string $connection) => $queues->map(fn (string $queue) => [
                'connection' => $connection,
                'queue' => $queue,
                'size' => $this->queue->connection($connection)->size($queue),
                'failed' => $this->failedJobs instanceof CountableFailedJobProvider
                    ? $this->failedJobs->count($connection, $queue)
                    : 0,
            ]));
    }
}

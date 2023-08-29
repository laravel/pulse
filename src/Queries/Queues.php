<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Collection;

/**
 * @interval
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
     * @return \Illuminate\Support\Collection<int, array{queue: string, size: int, failed: int}>
     */
    public function __invoke(): Collection
    {
        return collect($this->config->get('pulse.queues'))
            ->groupBy(fn ($value, $key) => is_int($key) ? $this->config->get('queue.default') : $key)
            ->map->flatten()
            ->flatMap(fn (Collection $queues, string $connection) => $queues->map(fn (string $queue) => [
                'connection' => $connection,
                'queue' => $queue,
                'size' => $this->queue->connection($connection)->size($queue),
                'supported' => $this->failedJobs instanceof CountableFailedJobProvider,
                'failed' => $this->failedJobs instanceof CountableFailedJobProvider
                    ? $this->failedJobs->count($connection, $queue)
                    : 0,
            ]));
    }
}

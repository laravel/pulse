<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Collection;
use stdClass;

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
            ->map(fn (string $queue) => [
                'queue' => $queue,
                'size' => $this->queue->size($queue),
                'failed' => collect($this->failedJobs->all())
                    ->filter(fn (stdClass $job) => $job->queue === $queue)
                    ->count(),
            ]);
    }
}


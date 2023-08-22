<?php

namespace Laravel\Pulse\Checks;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entries\Entry;
use stdClass;

/**
 * @internal
 */
class QueueSize
{
    public function __construct(
        protected Repository $config,
        protected QueueManager $queue,
        protected CacheManager $cache,
        protected FailedJobProviderInterface $failedJobs,
    ) {
        //
    }

    /**
     * Resolve the queue size.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>
     */
    public function __invoke(CarbonImmutable $now, Interval $interval): Collection
    {
        if (! $this->cache->lock("laravel:pulse:check-queue-size:{$now->timestamp}", (int) $interval->totalSeconds)->get()) {
            return collect();
        }

        return collect($this->config->get('pulse.queues'))->map(fn (string $queue) => new Entry('pulse_queue_sizes', [
            'date' => $now->toDateTimeString(),
            'queue' => $queue,
            'size' => $this->queue->size($queue),
            'failed' => collect($this->failedJobs->all())
                ->filter(fn (stdClass $job) => $job->queue === $queue)
                ->count(),
        ]));
    }
}

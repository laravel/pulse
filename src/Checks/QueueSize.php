<?php

namespace Laravel\Pulse\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;

class QueueSize
{
    public function __construct(
        protected Repository $config,
        protected QueueManager $queue,
    ) {
        //
    }

    /**
     * Resolve the queue size.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>
     */
    public function __invoke(CarbonImmutable $now): Collection
    {
        return collect($this->config->get('pulse.queues'))->map(fn ($queue) => new Entry(Table::QueueSize, [
            'date' => $now->toDateTimeString(),
            'queue' => $queue,
            'size' => $this->queue->size($queue),
            'failed' => collect(app('queue.failer')->all())
                ->filter(fn ($job) => $job->queue === $queue)
                ->count(),
        ]));
    }
}

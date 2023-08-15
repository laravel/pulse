<?php

namespace Laravel\Pulse\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;

class QueueSize
{
    /**
     * Resolve the queue size.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>
     */
    public function __invoke(CarbonImmutable $now): Collection
    {
        return collect(Config::get('pulse.queues'))->map(fn ($queue) => new Entry(Table::QueueSize, [
            'date' => $now->toDateTimeString(),
            'queue' => $queue,
            'size' => Queue::size($queue),
            'failed' => collect(app('queue.failer')->all())
                ->filter(fn ($job) => $job->queue === $queue)
                ->count(),
        ]));
    }
}

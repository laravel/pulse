<?php

namespace Laravel\Pulse\Checks;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

class QueueSize
{
    /**
     * Resolve the queue size.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function __invoke(): Collection
    {
        return collect(Config::get('pulse.queues'))->map(fn ($queue) => [
            'queue' => $queue,
            'size' => Queue::size($queue),
            'failed' => collect(app('queue.failer')->all())
                ->filter(fn ($job) => $job->queue === $queue)
                ->count(),
        ]);
    }
}

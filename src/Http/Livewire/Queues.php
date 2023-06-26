<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\Queue;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class Queues extends Component implements ShouldNotReportUsage
{
    public function render()
    {
        return view('pulse::livewire.queues', [
            'queues' => collect(config('pulse.queues'))->map(fn ($queue) => [
                'queue' => $queue,
                'size' => Queue::size($queue),
                'failed' => collect(app('queue.failer')->all())->filter(fn ($job) => $job->queue === $queue)->count(),
            ]),
        ]);
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class Queues extends Component
{
    use ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        return View::make('pulse::livewire.queues', [
            'queues' => collect(config('pulse.queues'))->map(fn ($queue) => [
                'queue' => $queue,
                'size' => Queue::size($queue),
                'failed' => collect(app('queue.failer')->all())->filter(fn ($job) => $job->queue === $queue)->count(),
            ]),
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', ['class' => 'col-span-3']);
    }
}

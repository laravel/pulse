<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Queues as QueuesRecorder;
use Livewire\Attributes\Lazy;

/**
 * @internal
 */
#[Lazy]
class Queues extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$queues, $time, $runAt] = $this->remember(fn () => Pulse::graph(
            ['queued', 'processing', 'processed', 'released', 'failed'],
            'count',
            $this->periodAsInterval(),
        ));

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch('queues-chart-update', queues: $queues);
        }

        return View::make('pulse::livewire.queues', [
            'queues' => $queues,
            'showConnection' => $queues->keys()->map(fn ($queue) => Str::before($queue, ':'))->unique()->count() > 1,
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.QueuesRecorder::class),
        ]);
    }
}

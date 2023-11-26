<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Queries\Queues as QueuesQuery;
use Laravel\Pulse\Recorders\Jobs;
use Livewire\Attributes\Lazy;

#[Lazy]
class Queues extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(QueuesQuery $query): Renderable
    {
        // [$queues, $time, $runAt] = $this->remember($query);

        [$queues, $time, $runAt] = $this->remember(fn () => Pulse::graph(
            ['queued:sum', 'processing:sum', 'processed:sum', 'released:sum', 'failed:sum'],
            $this->periodAsInterval(),
        ));

        if (request()->hasHeader('X-Livewire')) {
            $this->dispatch('queues-chart-update', queues: $queues);
        }

        return View::make('pulse::livewire.queues', [
            'queues' => $queues,
            'showConnection' => $queues->keys()->map(fn ($queue) => Str::before($queue, ':'))->unique()->count() > 1,
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.Jobs::class),
        ]);
    }
}

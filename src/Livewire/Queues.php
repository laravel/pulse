<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Queries\Queues as QueuesQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class Queues extends Card
{
    use Concerns\HasPeriod, Concerns\ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(QueuesQuery $query): Renderable
    {
        return View::make('pulse::livewire.queues', [
            'queues' => $queues = Cache::remember('laravel:pulse:queues', 5, fn () => $query($this->periodAsInterval())),
            'showConnection' => $queues->keys()->map(fn ($queue) => Str::before($queue, ':'))->unique()->count() > 1,
        ]);
    }
}

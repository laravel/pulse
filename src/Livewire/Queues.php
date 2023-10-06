<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Laravel\Pulse\Queries\Queues as QueuesQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class Queues extends Card
{
    use ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(QueuesQuery $query): Renderable
    {
        return View::make('pulse::livewire.queues', [
            'queues' => $queues = Cache::remember('laravel:pulse:queues:live', 5, fn () => $query()),
            'showConnection' => $queues->pluck('connection')->unique()->count() > 1,
        ]);
    }
}

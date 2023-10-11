<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\Servers as ServersQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class Servers extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries, Concerns\ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(ServersQuery $query): Renderable
    {
        [$servers, $time, $runAt] = $this->remember($query);

        if (request()->hasHeader('X-Livewire')) {
            $this->dispatch('servers-chart-update', servers: $servers);
        }

        return View::make('pulse::livewire.servers', [
            'servers' => $servers,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.servers-placeholder', ['cols' => $this->cols, 'rows' => $this->rows, 'class' => $this->class]);
    }
}

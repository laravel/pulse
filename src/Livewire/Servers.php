<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Laravel\Pulse\Queries\Servers as ServersQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class Servers extends Card
{
    use HasPeriod, ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(ServersQuery $query): Renderable
    {
        $servers = $query($this->periodAsInterval());

        if (request()->hasHeader('X-Livewire')) {
            $this->dispatch('chart-update', servers: $servers);
        }

        return View::make('pulse::livewire.servers', [
            'servers' => $servers,
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

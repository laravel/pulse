<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Laravel\Pulse\Queries\Servers as ServersQuery;
use Livewire\Component;

class Servers extends Component
{
    use HasPeriod, ShouldNotReportUsage;

    protected $servers;

    /**
     * Render the component.
     */
    public function render(callable $query): Renderable
    {
        return View::make('pulse::livewire.servers', [
            'servers' => $this->servers ??= $query($this->periodAsInterval()),
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', ['class' => 'col-span-6']);
    }

    /**
     * Update the chart.
     *
     * @TODO Binding...
     */
    public function updateChart(ServersQuery $query)
    {
        $this->dispatch('chartUpdate', servers: $this->servers ??= $query($this->periodAsInterval()));
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class Servers extends Component
{
    use HasPeriod, ShouldNotReportUsage;

    /**
     * The number of data points shown on the graph.
     */
    protected int $maxDataPoints = 60;

    /**
     * Render the component.
     */
    public function render(callable $query): Renderable
    {
        $servers = $query($this->periodAsInterval());

        if (request()->hasHeader('X-Livewire')) {
            $this->dispatch('chartUpdate', servers: $servers);
        }

        return view('pulse::livewire.servers', [
            'servers' => $servers,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return view('pulse::components.placeholder', ['class' => 'col-span-6']);
    }
}

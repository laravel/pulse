<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class Servers extends Component
{
    use HasPeriod, ShouldNotReportUsage;

    /**
     * The number of columns to span.
     */
    public int|string $cols = 'full';

    /**
     * Render the component.
     */
    public function render(callable $query): Renderable
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
        return View::make('pulse::components.servers-placeholder', ['cols' => $this->cols]);
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsServers;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class Servers extends Component
{
    use HasPeriod;
    use ShouldNotReportUsage;

    /**
     * The number of data points shown on the graph.
     */
    protected int $maxDataPoints = 60;

    /**
     * Render the component.
     */
    public function render(Storage $storage): Renderable
    {
        if (! $storage instanceof SupportsServers) {
            // TODO return an "unsupported" card.
            throw new RuntimeException('Storage driver does not support servers.');
        }

        $servers = $storage->servers($this->periodAsInterval());

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

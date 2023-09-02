<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class SlowRoutes extends Component
{
    use HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * The number of columns to span.
     */
    public int|string $cols = 3;

    /**
     * Render the component.
     */
    public function render(callable $query): Renderable
    {
        [$slowRoutes, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-routes', [
            'time' => $time,
            'runAt' => $runAt,
            'slowRoutes' => $slowRoutes,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', ['cols' => $this->cols]);
    }
}

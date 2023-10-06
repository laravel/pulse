<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Laravel\Pulse\Queries\SlowRoutes as SlowRoutesQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowRoutes extends Card
{
    use HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(SlowRoutesQuery $query): Renderable
    {
        [$slowRoutes, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-routes', [
            'time' => $time,
            'runAt' => $runAt,
            'slowRoutes' => $slowRoutes,
        ]);
    }
}

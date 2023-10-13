<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\SlowRoutes as SlowRoutesQuery;
use Laravel\Pulse\Recorders\Requests;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowRoutes extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries, Concerns\ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(SlowRoutesQuery $query): Renderable
    {
        [$slowRoutes, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-routes', [
            'time' => $time,
            'runAt' => $runAt,
            'threshold' => Config::get('pulse.recorders.'.Requests::class.'.threshold'),
            'slowRoutes' => $slowRoutes,
        ]);
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\SlowRequests as SlowRequestsQuery;
use Laravel\Pulse\Recorders\SlowRequests;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowRoutes extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(SlowRequestsQuery $query): Renderable
    {
        [$slowRoutes, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-routes', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.SlowRequests::class),
            'slowRoutes' => $slowRoutes,
        ]);
    }
}

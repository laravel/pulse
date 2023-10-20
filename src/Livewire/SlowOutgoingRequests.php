<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\SlowOutgoingRequests as SlowOutgoingRequestsQuery;
use Laravel\Pulse\Recorders\OutgoingRequests;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowOutgoingRequests extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(SlowOutgoingRequestsQuery $query): Renderable
    {
        [$slowOutgoingRequests, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.OutgoingRequests::class),
            'slowOutgoingRequests' => $slowOutgoingRequests,
            'supported' => method_exists(Factory::class, 'globalMiddleware'),
        ]);
    }
}

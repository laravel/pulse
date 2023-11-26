<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
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
        // [$slowOutgoingRequests, $time, $runAt] = $this->remember($query);

        [$slowOutgoingRequests, $time, $runAt] = $this->remember(fn () => Pulse::max('slow_outgoing_request', $this->periodAsInterval())->map(function ($row) {
            [$method, $uri] = explode(' ', $row->key, 2);

            return (object) [
                'method' => $method,
                'uri' => $uri,
                'slowest' => $row->max,
                'count' => $row->count,
            ];
        }));

        return View::make('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.OutgoingRequests::class),
            'slowOutgoingRequests' => $slowOutgoingRequests,
            'supported' => method_exists(Factory::class, 'globalMiddleware'),
        ]);
    }
}

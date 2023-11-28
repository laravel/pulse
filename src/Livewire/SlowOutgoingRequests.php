<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowOutgoingRequests as SlowOutgoingRequestsRecorder;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowOutgoingRequests extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$slowOutgoingRequests, $time, $runAt] = $this->remember(
            fn () => Pulse::aggregate('slow_outgoing_request', ['max', 'count'], $this->periodAsInterval())
                ->map(function ($row) {
                    [$method, $uri] = json_decode($row->key);

                    return (object) [
                        'method' => $method,
                        'uri' => $uri,
                        'slowest' => $row->max,
                        'count' => $row->count,
                    ];
                })
        );

        return View::make('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.SlowOutgoingRequestsRecorder::class),
            'slowOutgoingRequests' => $slowOutgoingRequests,
            'supported' => method_exists(Factory::class, 'globalMiddleware'),
        ]);
    }
}

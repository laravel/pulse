<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Laravel\Pulse\Queries\SlowOutgoingRequests as SlowOutgoingRequestsQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowOutgoingRequests extends Card
{
    use HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(SlowOutgoingRequestsQuery $query): Renderable
    {
        [$slowOutgoingRequests, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'slowOutgoingRequests' => $slowOutgoingRequests,
            'supported' => method_exists(Factory::class, 'globalMiddleware'),
        ]);
    }
}

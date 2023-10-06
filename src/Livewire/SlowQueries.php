<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Laravel\Pulse\Queries\SlowQueries as SlowQueriesQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowQueries extends Card
{
    use HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(SlowQueriesQuery $query): Renderable
    {
        [$slowQueries, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-queries', [
            'time' => $time,
            'runAt' => $runAt,
            'slowQueries' => $slowQueries,
        ]);
    }
}

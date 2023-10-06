<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Laravel\Pulse\Queries\SlowJobs as SlowJobsQuery;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowJobs extends Card
{
    use HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(SlowJobsQuery $query): Renderable
    {
        [$slowJobs, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-jobs', [
            'time' => $time,
            'runAt' => $runAt,
            'slowJobs' => $slowJobs,
        ]);
    }
}

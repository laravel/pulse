<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\SlowJobs as SlowJobsQuery;
use Laravel\Pulse\Recorders\Jobs;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowJobs extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries, Concerns\ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(SlowJobsQuery $query): Renderable
    {
        [$slowJobs, $time, $runAt] = $this->remember($query);

        return View::make('pulse::livewire.slow-jobs', [
            'time' => $time,
            'runAt' => $runAt,
            'threshold' => Config::get('pulse.recorders.'.Jobs::class.'.threshold'),
            'slowJobs' => $slowJobs,
        ]);
    }
}

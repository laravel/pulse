<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Queries\SlowJobs as SlowJobsQuery;
use Laravel\Pulse\Recorders\Jobs;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowJobs extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(SlowJobsQuery $query): Renderable
    {
        // [$slowJobs, $time, $runAt] = $this->remember($query);

        [$slowJobs, $time, $runAt] = $this->remember(fn () => Pulse::max('slow_job', $this->periodAsInterval())->map(fn ($row) => (object) [
            'job' => $row->key,
            'slowest' => $row->max,
            'count' => $row->count,
        ])
        );

        return View::make('pulse::livewire.slow-jobs', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.Jobs::class),
            'slowJobs' => $slowJobs,
        ]);
    }
}

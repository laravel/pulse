<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Jobs;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowJobs extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$slowJobs, $time, $runAt] = $this->remember(
            fn () => Pulse::aggregate('slow_job', ['max', 'count'], $this->periodAsInterval())
                ->map(fn ($row) => (object) [
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

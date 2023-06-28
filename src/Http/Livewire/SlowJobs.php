<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowJobs extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        if (request()->hasHeader('X-Livewire')) {
            $this->loadData();
        }

        [$slowJobs, $time, $runAt] = $this->slowJobs();

        return view('pulse::livewire.slow-jobs', [
            'time' => $time,
            'runAt' => $runAt,
            'slowJobs' => $slowJobs,
            'initialDataLoaded' => $slowJobs !== null,
        ]);
    }

    /**
     * The slow jobs.
     */
    protected function slowJobs(): array
    {
        return Cache::get("pulse:slow-jobs:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     */
    public function loadData(): void
    {
        Cache::remember("pulse:slow-jobs:{$this->period}", $this->periodCacheDuration(), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $slowJobs = DB::table('pulse_jobs')
                ->selectRaw('`job`, COUNT(*) as count, MAX(duration) AS slowest')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->where('duration', '>=', config('pulse.slow_job_threshold'))
                ->groupBy('job')
                ->orderByDesc('slowest')
                ->get()
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowJobs, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('slow-jobs:dataLoaded');
    }
}

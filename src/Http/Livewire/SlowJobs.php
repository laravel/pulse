<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowJobs extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render(Pulse $pulse)
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
     *
     * @return array
     */
    protected function slowJobs()
    {
        return Cache::get("pulse:slow-jobs:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     *
     * @return void
     */
    public function loadData()
    {
        Cache::remember("pulse:slow-jobs:{$this->period}", now()->addSeconds(match ($this->period) {
            '6_hours' => 30,
            '24_hours' => 60,
            '7_days' => 600,
            default => 5,
        }), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $slowJobs = DB::table('pulse_jobs')
                ->selectRaw('`job`, COUNT(*) as count, MAX(duration) AS slowest')
                ->where('date', '>=', $now->subHours(match ($this->period) {
                    '6_hours' => 6,
                    '24_hours' => 24,
                    '7_days' => 168,
                    default => 1,
                })->toDateTimeString())
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

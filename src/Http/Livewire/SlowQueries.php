<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowQueries extends Component implements ShouldNotReportUsage
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

        [$slowQueries, $time, $runAt] = $this->slowQueries();

        return view('pulse::livewire.slow-queries', [
            'time' => $time,
            'runAt' => $runAt,
            'slowQueries' => $slowQueries,
            'initialDataLoaded' => $slowQueries !== null,
        ]);
    }

    /**
     * The slow queries.
     *
     * @return array
     */
    protected function slowQueries()
    {
        return Cache::get("pulse:slow-queries:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     *
     * @return void
     */
    public function loadData()
    {
        Cache::remember("pulse:slow-queries:{$this->period}", now()->addSeconds(match ($this->period) {
            '6_hours' => 30,
            '24_hours' => 60,
            '7_days' => 600,
            default => 5,
        }), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $slowQueries = DB::table('pulse_queries')
                ->selectRaw('`sql`, COUNT(*) as count, MAX(duration) AS slowest')
                ->where('date', '>=', $now->subHours(match ($this->period) {
                    '6_hours' => 6,
                    '24_hours' => 24,
                    '7_days' => 168,
                    default => 1,
                })->toDateTimeString())
                ->where('duration', '>=', config('pulse.slow_query_threshold'))
                ->groupBy('sql')
                ->orderByDesc('slowest')
                ->get()
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowQueries, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('slow-queries:dataLoaded');
    }
}

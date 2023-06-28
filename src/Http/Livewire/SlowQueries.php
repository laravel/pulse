<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class SlowQueries extends Component implements ShouldNotReportUsage
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
     */
    protected function slowQueries(): array
    {
        return Cache::get("pulse:slow-queries:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     */
    public function loadData(): void
    {
        Cache::remember("pulse:slow-queries:{$this->period}", $this->periodCacheDuration(), function () {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowQueries = DB::table('pulse_queries')
                ->selectRaw('`sql`, COUNT(*) as count, MAX(duration) AS slowest')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
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

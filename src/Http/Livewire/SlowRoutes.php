<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class SlowRoutes extends Component implements ShouldNotReportUsage
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

        [$slowRoutes, $time, $runAt] = $this->slowRoutes();

        return view('pulse::livewire.slow-routes', [
            'time' => $time,
            'runAt' => $runAt,
            'slowRoutes' => $slowRoutes,
            'initialDataLoaded' => $slowRoutes !== null,
        ]);
    }

    /**
     * The slow routes.
     */
    protected function slowRoutes(): array
    {
        return Cache::get("illuminate:pulse:slow-routes:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     */
    public function loadData(): void
    {
        Cache::remember("illuminate:pulse:slow-routes:{$this->period}", $this->periodCacheDuration(), function () {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowRoutes = DB::table('pulse_requests')
                ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->where('duration', '>=', config('pulse.slow_endpoint_threshold'))
                ->groupBy('route')
                ->orderByDesc('slowest')
                ->get()
                ->map(function ($row) {
                    [$method, $path] = explode(' ', $row->route, 2);

                    $route = Route::getRoutes()->get($method)[$path] ?? null;

                    return [
                        'uri' => $row->route,
                        'action' => $route?->getActionName(),
                        'request_count' => (int) $row->count,
                        'slowest_duration' => (int) $row->slowest,
                    ];
                })
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowRoutes, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('slow-routes:dataLoaded');
    }
}

<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class SlowRoutes extends Component implements ShouldNotReportUsage
{
    /**
     * The usage period.
     *
     * @var string
     */
    public $period;

    /**
     * The event listeners.
     *
     * @var array
     */
    protected $listeners = [
        'periodChanged',
    ];

    /**
     * Handle the mount event.
     *
     * @return void
     */
    public function mount()
    {
        $this->period = request()->query('period') ?: '1-hour';
    }

    /**
     * Handle the periodChanged event.
     *
     * @param  string  $period
     * @return void
     */
    public function periodChanged(string $period)
    {
        $this->period = $period;
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        if (request()->hasHeader('X-Livewire')) {
            $this->loadData();
        }

        [$slowRoutes, $time] = $this->slowRoutes();

        return view('pulse::livewire.slow-routes', [
            'time' => $time,
            'slowRoutes' => $slowRoutes,
            'initialDataLoaded' => $slowRoutes !== null,
        ]);
    }

    /**
     * The slow routes.
     *
     * @return array
     */
    protected function slowRoutes()
    {
        return Cache::get("pulse:slow-routes:{$this->period}") ?? [null, 0];
    }

    /**
     * Load the data for the component.
     *
     * @return void
     */
    public function loadData()
    {
        Cache::remember("pulse:slow-routes:{$this->period}", now()->addSeconds(match ($this->period) {
            '6-hours' => 30,
            '24-hours' => 60,
            '7-days' => 600,
            default => 5,
        }), function () {
            $start = hrtime(true);

            $slowRoutes = DB::table('pulse_requests')
                ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest')
                ->where('date', '>=', now()->subHours(match ($this->period) {
                    '6-hours' => 6,
                    '24-hours' => 24,
                    '7-days' => 168,
                    default => 1,
                })->toDateTimeString())
                ->where('duration', '>=', config('pulse.slow_endpoint_threshold'))
                ->groupBy('route')
                ->orderByDesc('slowest')
                ->limit(10)
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

            return [$slowRoutes, $time];
        });

        $this->dispatchBrowserEvent('slow-routes:dataLoaded');
    }
}

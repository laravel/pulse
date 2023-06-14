<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class SlowRoutes extends Component implements ShouldNotReportUsage
{
    public $period;

    protected $listeners = ['periodChanged'];

    public function mount()
    {
        $this->period = request()->query('period') ?? '1-hour';
    }

    public function render()
    {
        $from = now()->subHours(match ($this->period) {
            '6-hours' => 6,
            '24-hours' => 24,
            '7-days' => 168,
            default => 1,
        });

        [$routes, $time] = Cache::remember(
            'pulse:slow-routes:' . ($this->period ?? '1-hour'),
            now()->addSeconds(match ($this->period) {
                '6-hours' => 30,
                '24-hours' => 60,
                '7-days' => 600,
                default => 5,
            }),
            function () use ($from) {
                $start = hrtime(true);

                $routes = DB::table('pulse_requests')
                    ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest')
                    ->where('date', '>=', $from->toDateTimeString())
                    ->where('duration', '>=', config('pulse.slow_endpoint_threshold'))
                    ->groupBy('route')
                    ->orderByDesc('slowest')
                    ->limit(10)
                    ->get()
                    ->map(function ($row) {
                        $method = substr($row->route, 0, strpos($row->route, ' '));
                        $path = substr($row->route, strpos($row->route, '/') + 1);
                        $route = Route::getRoutes()->get($method)[$path] ?? null;

                        return [
                            'uri' => $row->route,
                            'action' => $route?->getActionName(),
                            'request_count' => (int) $row->count,
                            'slowest_duration' => (int) $row->slowest,
                        ];
                    });

                $time = (hrtime(true) - $start) / 1000000;

                return [$routes, $time];
            }
        );

        return view('pulse::livewire.slow-routes', [
            'routes' => $routes,
            'time' => $time,
        ]);
    }

    public function periodChanged(string $period)
    {
        $this->period = $period;
    }
}

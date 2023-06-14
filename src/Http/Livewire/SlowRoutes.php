<?php

namespace Laravel\Pulse\Http\Livewire;

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

        $threshold = config('pulse.slow_endpoint_threshold');

        $routes = DB::table('pulse_requests')
            ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>=', $from->toDateTimeString())
            ->where('duration', '>=', $threshold)
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

        return view('pulse::livewire.slow-routes', [
            'routes' => $routes,
        ]);
    }

    public function periodChanged(string $period)
    {
        $this->period = $period;
    }
}

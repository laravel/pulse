<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowEndpoints extends Component implements ShouldNotReportUsage
{
    public $period;

    protected $queryString = ['period'];

    public function render(Pulse $pulse)
    {
        $from = now()->subHours(match ($this->period) {
            '6-hours' => 6,
            '24-hours' => 24,
            '7-days' => 168,
            default => 1,
        });

        $threshold = 1000;

        $slowEndpoints = DB::table('pulse_requests')
            ->selectRaw('route, COUNT(*) as count, MAX(duration) AS slowest, AVG(duration) AS average')
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
                    'average_duration' => (int) $row->average,
                ];
            });

        return view('pulse::livewire.slow-endpoints', [
            'slowEndpoints' => $slowEndpoints,
        ]);
    }
}

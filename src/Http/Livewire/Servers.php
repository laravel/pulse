<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class Servers extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    public function render()
    {
        $maxDataPoints = 60;
        $servers = $this->servers($maxDataPoints);

        if (request()->hasHeader('X-Livewire')) {
            $this->emit('chartUpdate', $servers);
        }

        return view('pulse::livewire.servers', [
            'servers' => $servers,
        ]);
    }

    protected function servers($maxDataPoints)
    {
        return DB::table('pulse_servers')
            ->selectRaw('
                MAX(date) AS date,
                server,
                ROUND(AVG(cpu_percent)) AS cpu_percent,
                ROUND(AVG(memory_used)) AS memory_used,
                MAX(memory_total) AS memory_total,
                MAX(storage) AS storage
            ')
            ->orderByDesc('date')
            ->when(true, fn ($query) => match ($this->period) {
                '7_days' => $query
                    ->where('date', '>=', now()->subDays(7))
                    ->groupByRaw('server, ROUND(UNIX_TIMESTAMP(date) / ?)', [ 604800 / $maxDataPoints ]),
                '24_hours' => $query
                    ->where('date', '>=', now()->subHours(24))
                    ->groupByRaw('server, ROUND(UNIX_TIMESTAMP(date) / ?)', [ 86400 / $maxDataPoints ]),
                '6_hours' => $query
                    ->where('date', '>=', now()->subHours(6))
                    ->groupByRaw('server, ROUND(UNIX_TIMESTAMP(date) / ?)', [ 21600 / $maxDataPoints ]),
                default => $query
                    ->where('date', '>=', now()->subHour())
                    ->groupByRaw('server, ROUND(UNIX_TIMESTAMP(date) / ?)', [ 3600 / $maxDataPoints ])
            })
            ->limit($maxDataPoints)
            ->get()
            ->reverse()
            ->groupBy('server')
            ->mapWithKeys(function ($readings, $server) {
                return [Str::slug($server) => [
                    'name' => $server,
                    'readings' => collect($readings)->map(fn ($reading) => (object) [
                        ...((array) $reading),
                        'storage' => json_decode($reading->storage),
                    ]),
                ]];
            });
    }
}

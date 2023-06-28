<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
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
        $currentBucket = CarbonImmutable::createFromTimestamp(
            floor(now()->getTimestamp() / ($this->periodSeconds() / $maxDataPoints)) * ($this->periodSeconds() / $maxDataPoints)
        );

        $secondsPerPeriod = $this->periodSeconds() / $maxDataPoints;

        $padding = collect()
            ->pad(60, null)
            ->map(fn ($value, $i) => (object) [
                'date' => $currentBucket->subSeconds($i * $secondsPerPeriod)->format('Y-m-d H:i'),
                'cpu_percent' => null,
                'memory_used' => null,
            ])
            ->reverse()
            ->keyBy('date');

        $serverReadings = DB::query()
            ->select('bucket', 'server')
            ->selectRaw('ROUND(AVG(`cpu_percent`)) AS `cpu_percent`')
            ->selectRaw('ROUND(AVG(`memory_used`)) AS `memory_used`')
            ->fromSub(
                fn ($query) => $query
                    ->from('pulse_servers')
                    ->select(['server', 'cpu_percent', 'memory_used', 'date'])
                    // Divide the data into buckets.
                    ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`date`, ?, @@session.time_zone)) / ?) AS `bucket`', [now()->format('P'), $secondsPerPeriod])
                    ->where('date', '>=', now()->subSeconds($this->periodSeconds())),
                'grouped'
            )
            ->groupBy('server', 'bucket')
            ->orderByDesc('bucket')
            ->limit($maxDataPoints)
            ->get()
            ->reverse()
            ->groupBy('server')
            ->map(function ($readings) use ($secondsPerPeriod, $padding) {
                $readings = $readings->keyBy(fn ($reading) => CarbonImmutable::createFromTimestamp($reading->bucket * $secondsPerPeriod)->format('Y-m-d H:i'));

                return $padding->merge($readings)->values();
            });

        return DB::table('pulse_servers')
            // Get the latest row for every server, even if it hasn't reported in the selected period.
            ->joinSub(
                DB::table('pulse_servers')
                    ->selectRaw('server, MAX(date) AS date')
                    ->groupBy('server'),
                'grouped',
                fn ($join) => $join
                    ->on('pulse_servers.server', '=', 'grouped.server')
                    ->on('pulse_servers.date', '=', 'grouped.date')
            )
            ->get()
            ->map(fn ($server) => (object) [
                'name' => $server->server,
                'slug' => Str::slug($server->server),
                'cpu_percent' => $server->cpu_percent,
                'memory_used' => $server->memory_used,
                'memory_total' => $server->memory_total,
                'storage' => json_decode($server->storage),
                'readings' => $serverReadings->get($server->server)?->map(fn ($reading) => (object) [
                    'cpu_percent' => $reading->cpu_percent,
                    'memory_used' => $reading->memory_used,
                ])->all() ?? [],
                'updated_at' => $updatedAt = CarbonImmutable::parse($server->date),
                'recently_reported' => $updatedAt->isAfter(now()->subSeconds(30)),
            ])
            ->keyBy('slug');
    }
}

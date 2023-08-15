<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class Servers extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * The number of data points shown on the graph.
     */
    protected int $maxDataPoints = 60;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        $servers = $this->servers();

        if (request()->hasHeader('X-Livewire')) {
            $this->dispatch('chartUpdate', servers: $servers);
        }

        return view('pulse::livewire.servers', [
            'servers' => $servers,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return view('pulse::components.placeholder', ['class' => 'col-span-6']);
    }

    /**
     * The server statistics.
     */
    protected function servers(): Collection
    {
        $now = new CarbonImmutable();

        $currentBucket = CarbonImmutable::createFromTimestamp(
            floor($now->getTimestamp() / ($this->periodSeconds() / $this->maxDataPoints)) * ($this->periodSeconds() / $this->maxDataPoints)
        );

        $secondsPerPeriod = $this->periodSeconds() / $this->maxDataPoints;

        $padding = collect([])
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
            ->when(true, fn ($query) => match (config('pulse.graph_aggregation')) {
                'max' => $query
                    ->selectRaw('ROUND(MAX(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(MAX(`memory_used`)) AS `memory_used`'),
                default => $query
                    ->selectRaw('ROUND(AVG(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(AVG(`memory_used`)) AS `memory_used`')
            })
            ->fromSub(
                fn ($query) => $query
                    ->from('pulse_servers')
                    ->select(['server', 'cpu_percent', 'memory_used', 'date'])
                    // Divide the data into buckets.
                    ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`date`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                    ->where('date', '>=', $now->subSeconds($this->periodSeconds())),
                'grouped'
            )
            ->groupBy('server', 'bucket')
            ->orderByDesc('bucket')
            ->limit($this->maxDataPoints)
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
                'storage' => json_decode($server->storage, flags: JSON_THROW_ON_ERROR),
                'readings' => $serverReadings->get($server->server)?->map(fn ($reading) => (object) [
                    'cpu_percent' => $reading->cpu_percent,
                    'memory_used' => $reading->memory_used,
                ])->all() ?? [],
                'updated_at' => $updatedAt = CarbonImmutable::parse($server->date),
                'recently_reported' => $updatedAt->isAfter($now->subSeconds(30)),
            ])
            ->keyBy('slug');
    }
}

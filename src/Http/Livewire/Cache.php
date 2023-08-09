<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class Cache extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$allCacheInteractions, $allTime, $allRunAt] = $this->allCacheInteractions();

        [$monitoredCacheInteractions, $monitoredTime, $monitoredRunAt] = $this->monitoredCacheInteractions();

        $this->dispatch('cache:dataLoaded');

        return view('pulse::livewire.cache', [
            'allTime' => $allTime,
            'allRunAt' => $allRunAt,
            'monitoredTime' => $monitoredTime,
            'monitoredRunAt' => $monitoredRunAt,
            'allCacheInteractions' => $allCacheInteractions,
            'monitoredCacheInteractions' => $monitoredCacheInteractions,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder()
    {
        return view('pulse::components.placeholder', ['class' => 'col-span-3']);
    }

    /**
     * All the cache interactions.
     */
    protected function allCacheInteractions(): array
    {
        return CacheFacade::remember("pulse:cache-all:{$this->period}", $this->periodCacheDuration(), function () {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $cacheInteractions = DB::table('pulse_cache_hits')
                ->selectRaw('COUNT(*) AS count, SUM(CASE WHEN `hit` = TRUE THEN 1 ELSE 0 END) as hits')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->first();

            $cacheInteractions->hits = (int) $cacheInteractions->hits;

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$cacheInteractions, $time, $now->toDateTimeString()];
        });
    }

    /**
     * The monitored cache interactions.
     */
    protected function monitoredCacheInteractions(): array
    {
        return CacheFacade::remember("pulse:cache-monitored:{$this->period}:{$this->monitoredKeysCacheHash()}", $this->periodCacheDuration(), function () {
            $now = new CarbonImmutable;

            if ($this->monitoredKeys()->isEmpty()) {
                return [[], 0, $now->toDateTimeString()];
            }

            $start = hrtime(true);

            $interactions = $this->monitoredKeys()->mapWithKeys(fn ($name, $regex) => [
                $name => (object) [
                    'regex' => $regex,
                    'key' => $name,
                    'uniqueKeys' => 0,
                    'hits' => 0,
                    'count' => 0,
                ],
            ]);

            DB::table('pulse_cache_hits')
                ->selectRaw('`key`, COUNT(*) AS count, SUM(CASE WHEN `hit` = TRUE THEN 1 ELSE 0 END) as hits')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                // TODO: ensure PHP and MySQL regex is compatible
                // TODO modifiers? is redis / memcached / etc case sensitive?
                ->where(fn ($query) => $this->monitoredKeys()->keys()->each(fn ($key) => $query->orWhere('key', 'RLIKE', $key)))
                ->orderBy('key')
                ->groupBy('key')
                ->each(function ($result) use ($interactions) {
                    $name = $this->monitoredKeys()->firstWhere(fn ($name, $regex) => preg_match('/'.$regex.'/', $result->key) > 0);

                    if ($name === null) {
                        return;
                    }

                    $interaction = $interactions[$name];

                    $interaction->uniqueKeys++;
                    $interaction->hits += $result->hits;
                    $interaction->count += $result->count;
                });

            $monitoringIndex = $this->monitoredKeys()->values()->flip();

            $interactions = $interactions
                ->sortBy(fn ($interaction) => $monitoringIndex[$interaction->key])
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$interactions, $time, $now->toDateTimeString()];
        });
    }

    /** The monitored keys.
     */
    protected function monitoredKeys(): Collection
    {
        return collect(config('pulse.cache_keys') ?? [])
            ->mapWithKeys(fn ($value, $key) => is_string($key)
                ? [$key => $value]
                : [$value => $value]);
    }

    /**
     * The monitored keys cache hash.
     */
    protected function monitoredKeysCacheHash(): string
    {
        return $this->monitoredKeys()->pipe(fn ($items) => md5($items->toJson()));
    }
}

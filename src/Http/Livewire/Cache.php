<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Contracts\Support\Renderable;
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
        if (request()->hasHeader('X-Livewire')) {
            $this->loadData();
        }

        [$allCacheInteractions, $time, $runAt] = $this->allCacheInteractions();

        [$monitoredCacheInteractions, $time, $runAt] = $this->monitoredCacheInteractions();

        return view('pulse::livewire.cache', [
            'time' => $time,
            'runAt' => $runAt,
            'allCacheInteractions' => $allCacheInteractions,
            'monitoredCacheInteractions' => $monitoredCacheInteractions,
            'initialDataLoaded' => $allCacheInteractions !== null,
        ]);
    }

    /**
     * All the cache interactions.
     */
    protected function allCacheInteractions(): array
    {
        return CacheFacade::get("pulse:cache-all:{$this->period}") ?? [null, 0, null];
    }

    /**
     * The monitored cache interactions.
     */
    protected function monitoredCacheInteractions(): array
    {
        $monitoring = collect(config('pulse.cache_keys') ?? [])
            ->mapWithKeys(fn ($value, $key) => is_string($key)
            ? [$key => $value]
            : [$value => $value]);

        $hash = md5($monitoring->toJson());

        return CacheFacade::get("pulse:cache-monitored:{$this->period}:{$hash}") ?? [[], 0, null];
    }

    /**
     * Load the data for the component.
     */
    public function loadData(): void
    {
        CacheFacade::remember("pulse:cache-all:{$this->period}", $this->periodCacheDuration(), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $cacheInteractions = DB::table('pulse_cache_hits')
                ->selectRaw('COUNT(*) AS count, SUM(CASE WHEN `hit` = TRUE THEN 1 ELSE 0 END) as hits')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->first();

            $cacheInteractions->hits = (int) $cacheInteractions->hits;

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$cacheInteractions, $time, $now->toDateTimeString()];
        });

        // TODO: bust cache if mointored keys change.

        $monitoring = collect(config('pulse.cache_keys') ?? [])
            ->mapWithKeys(fn ($value, $key) => is_string($key)
            ? [$key => $value]
            : [$value => $value]);

        $hash = md5($monitoring->toJson());

        CacheFacade::remember("pulse:cache-monitored:{$this->period}:{$hash}", $this->periodCacheDuration(), function () use ($monitoring) {
            $now = now()->toImmutable();

            if ($monitoring->isEmpty()) {
                return [[], 0, $now->toDateTimeString()];
            }

            $start = hrtime(true);

            $interactions = $monitoring->mapWithKeys(fn ($name, $regex) => [
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
                ->where(fn ($query) => $monitoring->keys()->each(fn ($key) => $query->orWhere('key', 'RLIKE', $key)))
                ->orderBy('key')
                ->groupBy('key')
                ->each(function ($result) use ($monitoring, $interactions) {
                    $name = $monitoring->firstWhere(fn ($name, $regex) => preg_match('/'.$regex.'/', $result->key) > 0);

                    if ($name === null) {
                        return;
                    }

                    $interaction = $interactions[$name];

                    $interaction->uniqueKeys++;
                    $interaction->hits += $result->hits;
                    $interaction->count += $result->count;
                });

            $monitoringIndex = $monitoring->values()->flip();

            $interactions = $interactions->sortBy(fn ($interaction) => $monitoringIndex[$interaction->key]);

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$interactions, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('cache:dataLoaded');
    }
}

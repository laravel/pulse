<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class Cache extends Component
{
    use HasPeriod, ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(callable $cacheInteractionsQuery, callable $monitoredCacheInteractionsQuery): Renderable
    {
        [$cacheInteractions, $allTime, $allRunAt] = $this->cacheInteractions($cacheInteractionsQuery);

        [$monitoredCacheInteractions, $monitoredTime, $monitoredRunAt] = $this->monitoredCacheInteractions($monitoredCacheInteractionsQuery);

        $this->dispatch('cache:dataLoaded');

        return View::make('pulse::livewire.cache', [
            'allTime' => $allTime,
            'allRunAt' => $allRunAt,
            'monitoredTime' => $monitoredTime,
            'monitoredRunAt' => $monitoredRunAt,
            'allCacheInteractions' => $cacheInteractions,
            'monitoredCacheInteractions' => $monitoredCacheInteractions,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', ['class' => 'col-span-3']);
    }

    /**
     * All the cache interactions.
     */
    protected function cacheInteractions(callable $query): array
    {
        return CacheFacade::remember("laravel:pulse:cache-all:{$this->period}", $this->periodCacheDuration(), function () use ($query) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $cacheInteractions = $query($this->periodAsInterval());

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$cacheInteractions, $time, $now->toDateTimeString()];
        });
    }

    /**
     * The monitored cache interactions.
     */
    protected function monitoredCacheInteractions(callable $query): array
    {
        return CacheFacade::remember("laravel:pulse:cache-monitored:{$this->period}:{$this->monitoredKeysCacheHash()}", $this->periodCacheDuration(), function () use ($query) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $interactions = $query($this->periodAsInterval(), $this->monitoredKeys());

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$interactions, $time, $now->toDateTimeString()];
        });
    }

    /** The monitored keys.
     */
    protected function monitoredKeys(): Collection
    {
        return collect(config('pulse.cache_keys'))
            ->mapWithKeys(fn (string $value, int|string $key) => is_string($key)
                ? [$key => $value]
                : [$value => $value]);
    }

    /**
     * The monitored keys cache hash.
     */
    protected function monitoredKeysCacheHash(): string
    {
        return $this->monitoredKeys()->pipe(fn (Collection $items) => md5($items->toJson()));
    }
}

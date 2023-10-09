<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\CacheInteractions;
use Laravel\Pulse\Queries\MonitoredCacheInteractions;
use Livewire\Attributes\Lazy;

#[Lazy]
class Cache extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries, Concerns\ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(CacheInteractions $cacheInteractionsQuery, MonitoredCacheInteractions $monitoredCacheInteractionsQuery): Renderable
    {
        $monitoring = collect(Config::get('pulse.cache_keys'))
            ->mapWithKeys(fn (string $value, int|string $key) => is_string($key)
                ? [$key => $value]
                : [$value => $value]);

        [$cacheInteractions, $allTime, $allRunAt] = $this->remember($cacheInteractionsQuery);

        [$monitoredCacheInteractions, $monitoredTime, $monitoredRunAt] = $this->remember(
            fn ($interval) => $monitoredCacheInteractionsQuery($interval, $monitoring),
            md5($monitoring->toJson())
        );

        return View::make('pulse::livewire.cache', [
            'allTime' => $allTime,
            'allRunAt' => $allRunAt,
            'monitoredTime' => $monitoredTime,
            'monitoredRunAt' => $monitoredRunAt,
            'allCacheInteractions' => $cacheInteractions,
            'monitoredCacheInteractions' => $monitoredCacheInteractions,
        ]);
    }
}

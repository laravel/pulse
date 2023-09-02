<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class Cache extends Component
{
    use HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * The number of columns to span.
     */
    public int|string $cols = 3;

    /**
     * Render the component.
     */
    public function render(callable $cacheInteractionsQuery, callable $monitoredCacheInteractionsQuery): Renderable
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

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', ['cols' => $this->cols]);
    }
}

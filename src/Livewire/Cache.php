<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\CacheInteractions;
use Laravel\Pulse\Queries\CacheKeyInteractions;
use Laravel\Pulse\Recorders\CacheInteractions as CacheInteractionsRecorder;
use Livewire\Attributes\Lazy;

#[Lazy]
class Cache extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(CacheInteractions $cacheInteractionsQuery, CacheKeyInteractions $cacheKeyInteractionsQuery): Renderable
    {
        [$cacheInteractions, $allTime, $allRunAt] = $this->remember($cacheInteractionsQuery, 'all');

        [$cacheKeyInteractions, $keyTime, $keyRunAt] = $this->remember($cacheKeyInteractionsQuery, 'keys');

        return View::make('pulse::livewire.cache', [
            'allTime' => $allTime,
            'allRunAt' => $allRunAt,
            'allCacheInteractions' => $cacheInteractions,
            'keyTime' => $keyTime,
            'keyRunAt' => $keyRunAt,
            'cacheKeyInteractions' => $cacheKeyInteractions,
            'groups' => Config::get('pulse.recorders.'.CacheInteractionsRecorder::class.'.groups'),
        ]);
    }
}

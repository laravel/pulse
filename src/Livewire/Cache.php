<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Recorders\CacheInteractions as CacheInteractionsRecorder;
use Livewire\Attributes\Lazy;

#[Lazy]
class Cache extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$cacheInteractions, $allTime, $allRunAt] = $this->remember(
            function () {
                $results = app(\Laravel\Pulse\Contracts\Storage::class)->sumMulti(
                    ['cache_hit', 'cache_miss'],
                    $this->periodAsInterval(),
                    groupKeys: false,
                )->pluck('sum', 'type');

                return (object) [
                    'hits' => $results['cache_hit'],
                    'misses' => $results['cache_miss'],
                ];
            },
            'all'
        );

        [$cacheKeyInteractions, $keyTime, $keyRunAt] = $this->remember(
            function () {
                return app(\Laravel\Pulse\Contracts\Storage::class)->sumMulti(
                    ['cache_hit', 'cache_miss'],
                    $this->periodAsInterval(),
                )->map(function ($row) {
                    return (object) [
                        'key' => $row->key,
                        'hits' => $row->cache_hit,
                        'misses' => $row->cache_miss,
                    ];
                });
            },
            'keys'
        );

        return View::make('pulse::livewire.cache', [
            'allTime' => $allTime,
            'allRunAt' => $allRunAt,
            'allCacheInteractions' => $cacheInteractions,
            'keyTime' => $keyTime,
            'keyRunAt' => $keyRunAt,
            'cacheKeyInteractions' => $cacheKeyInteractions,
            'config' => Config::get('pulse.recorders.'.CacheInteractionsRecorder::class),
        ]);
    }
}

<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Cache;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Cache::class);
});

it('renders cache statistics', function () {
    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('cache_hit', 'foo')->sum();
    Pulse::record('cache_hit', 'bar')->sum();
    Pulse::record('cache_miss', 'foo')->sum();
    Pulse::record('cache_miss', 'bar')->sum();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('cache_hit', 'foo')->sum();
    Pulse::record('cache_hit', 'foo')->sum();
    Pulse::record('cache_hit', 'bar')->sum();
    Pulse::record('cache_miss', 'foo')->sum();
    Pulse::record('cache_miss', 'foo')->sum();
    Pulse::record('cache_miss', 'bar')->sum();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('cache_hit', 'foo')->sum();
    Pulse::record('cache_hit', 'foo')->sum();
    Pulse::record('cache_hit', 'bar')->sum();
    Pulse::record('cache_miss', 'foo')->sum();
    Pulse::record('cache_miss', 'foo')->sum();
    Pulse::record('cache_miss', 'bar')->sum();

    Pulse::store();

    Livewire::test(Cache::class, ['lazy' => false])
        ->assertViewHas('allCacheInteractions', (object) [
            'hits' => 6,
            'misses' => 6,
        ])
        ->assertViewHas('cacheKeyInteractions', collect([
            (object) ['key' => 'foo', 'hits' => 4, 'misses' => 4],
            (object) ['key' => 'bar', 'hits' => 2, 'misses' => 2],
        ]));
});

<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Cache;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(Cache::class);
});

it('renders cache statistics', function () {
    Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->insert([
        ['date' => '2000-01-02 03:04:05', 'key' => 'foo', 'hit' => true],
        ['date' => '2000-01-02 03:04:05', 'key' => 'foo', 'hit' => true],
        ['date' => '2000-01-02 03:04:05', 'key' => 'foo', 'hit' => false],
        ['date' => '2000-01-02 03:04:05', 'key' => 'foo', 'hit' => false],
        ['date' => '2000-01-02 03:04:05', 'key' => 'bar', 'hit' => true],
        ['date' => '2000-01-02 03:04:05', 'key' => 'bar', 'hit' => false],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:10');

    Livewire::test(Cache::class, ['lazy' => false])
        ->assertViewHas('allTime')
        ->assertViewHas('allRunAt', '2000-01-02 03:04:10')
        ->assertViewHas('allCacheInteractions', (object) [
            'count' => 6,
            'hits' => 3,
        ])
        ->assertViewHas('keyTime')
        ->assertViewHas('keyRunAt', '2000-01-02 03:04:10')
        ->assertViewHas('cacheKeyInteractions', collect([
            (object) ['key' => 'foo', 'count' => 4, 'hits' => 2],
            (object) ['key' => 'bar', 'count' => 2, 'hits' => 1],
        ]))
        ->assertViewHas('config');
});

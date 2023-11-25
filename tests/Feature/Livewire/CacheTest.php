<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Cache;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Cache::class);
});

it('renders cache statistics', function () {
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_hit', 'key' => 'foo', 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_hit', 'key' => 'foo', 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_hit', 'key' => 'bar', 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_miss', 'key' => 'foo', 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_miss', 'key' => 'foo', 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_miss', 'key' => 'bar', 'value' => 1],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'cache_hit:sum', 'key' => 'foo', 'value' => 2, 'count' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'cache_hit:sum', 'key' => 'bar', 'value' => 1, 'count' => 1],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'cache_miss:sum', 'key' => 'foo', 'value' => 2, 'count' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'cache_miss:sum', 'key' => 'bar', 'value' => 1, 'count' => 1],
    ]));

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

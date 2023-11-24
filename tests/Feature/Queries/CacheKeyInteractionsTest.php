<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Queries\CacheKeyInteractions;

it('can get the data', function () {
    $query = App::make(CacheKeyInteractions::class);
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 59, 'type' => 'cache_hit', 'key' => 'users:{user}'],
        ['timestamp' => $timestamp - 3600 + 59, 'type' => 'cache_miss', 'key' => 'users:{user}'],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_hit', 'key' => 'users:{user}'],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'cache_miss', 'key' => 'users:{user}'],
    ]);
    $currentBucket = floor($timestamp / 60) * 60;
    DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket - 59, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 58, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 57, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 56, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 55, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 54, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 53, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 52, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 51, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 50, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 49, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 48, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 47, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 46, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 45, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 44, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 43, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 42, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 41, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 40, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 39, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 38, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 37, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 36, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 35, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 34, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 33, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 32, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 31, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 30, 'period' => 60, 'type' => 'cache_miss:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 29, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 28, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 27, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 26, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 25, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 24, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 23, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 22, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 21, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 20, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 19, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 18, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 17, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 16, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 15, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 14, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 13, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 12, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 11, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 10, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 9, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 8, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 7, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 6, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 5, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 4, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 3, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 2, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket - 1, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 10],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'cache_hit:count', 'key' => 'users:{user}', 'value' => 5],
    ]);

    $results = $query(CarbonInterval::hour());

    expect($results)->toHaveCount(1);
    expect($results[0])->toHaveProperties([
        'key' => 'users:{user}',
        'hits' => 296,
        'misses' => 301,
    ]);
});

it('limits to 101 records', function () {
    $query = App::make(CacheKeyInteractions::class);
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    for ($i = 0; $i < 200; $i++) {
        DB::table('pulse_aggregates')->insert([
            'bucket' => (int) floor($timestamp / 60) * 60,
            'period' => 60,
            'type' => 'cache_hit:count',
            'key' => Str::random(),
            'value' => rand(1, 10),
        ]);
    }

    $results = $query(CarbonInterval::hour());

    expect($results)->toHaveCount(101);
});

<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Queries\CacheKeyInteractions;

it('can get the data', function () {
    $query = App::make(CacheKeyInteractions::class);
    DB::table('pulse_cache_interactions')->insert([
        [
            'date' => $date = now()->toDateTimeString(),
            'hit' => true,
            'key' => 'users:{user}:avatar',
        ],
        [
            'date' => $date,
            'hit' => true,
            'key' => 'users:{user}:profile',
        ],
        [
            'date' => $date,
            'hit' => true,
            'key' => 'users:{user}:profile',
        ],
        [
            'date' => $date,
            'hit' => true,
            'key' => 'users:{user}:profile',
        ],
        [
            'date' => $date,
            'hit' => false,
            'key' => 'users:{user}:avatar',
        ],
        [
            'date' => $date,
            'hit' => false,
            'key' => 'users:{user}:avatar',
        ],
        [
            'date' => $date,
            'hit' => false,
            'key' => 'users:{user}:avatar',
        ],
        [
            'date' => $date = now()->toDateTimeString(),
            'hit' => true,
            'key' => 'users:{user}:avatar',
        ],
    ]);

    $results = $query(CarbonInterval::hour());

    expect($results)->toHaveCount(2);
    expect($results[0])->toHaveProperties([
        'key' => 'users:{user}:avatar',
        'count' => 5,
        'hits' => '2',
    ]);
    expect($results[1])->toHaveProperties([
        'key' => 'users:{user}:profile',
        'count' => 3,
        'hits' => '3',
    ]);
});

it('contains records to interval', function () {
    $query = App::make(CacheKeyInteractions::class);
    DB::table('pulse_cache_interactions')->insert([
        [
            'date' => '2000-01-01 00:00:05',
            'hit' => true,
            'key' => 'before',
        ],
        [
            'date' => '2000-01-01 00:00:06',
            'hit' => true,
            'key' => 'after',
        ],
    ]);
    Carbon::setTestNow('2000-01-01 01:00:05');

    $results = $query(CarbonInterval::hour());

    expect($results)->toHaveCount(1);
    expect($results[0]->key)->toBe('after');
});

it('limits to 101 records', function () {
    $query = App::make(CacheKeyInteractions::class);
    for ($i = 0; $i < 200; $i++) {
        DB::table('pulse_cache_interactions')->insert([
            'date' => now()->toDateTimeString(),
            'hit' => true,
            'key' => Str::random(),
        ]);
    }

    $results = $query(CarbonInterval::hour());

    expect($results)->toHaveCount(101);
});

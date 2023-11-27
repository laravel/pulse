<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Ingests\Storage as StorageIngest;

test('one or more aggregates for a single type', function () {
    /*
    | key      | sum  | max | avg | count |
    |----------|------|-----|-----|-------|
    | GET /bar | 2400 | 600 | 400 | 6     |
    | GET /foo | 1200 | 300 | 200 | 6     |
    */

    // Add entries outside of the window
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_request', 'GET /foo', 100)->max()->avg()->sum();
    Pulse::record('slow_request', 'GET /bar', 200)->max()->avg()->sum();

    // Add entries to the "tail"
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_request', 'GET /foo', 100)->max()->avg()->sum();
    Pulse::record('slow_request', 'GET /bar', 200)->max()->avg()->sum();
    Carbon::setTestNow('2000-01-01 12:00:02');
    Pulse::record('slow_request', 'GET /foo', 200)->max()->avg()->sum();
    Pulse::record('slow_request', 'GET /bar', 400)->max()->avg()->sum();
    Carbon::setTestNow('2000-01-01 12:00:03');
    Pulse::record('slow_request', 'GET /foo', 300)->max()->avg()->sum();
    Pulse::record('slow_request', 'GET /bar', 600)->max()->avg()->sum();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 12:59:00');
    Pulse::record('slow_request', 'GET /foo', 100)->max()->avg()->sum();
    Pulse::record('slow_request', 'GET /bar', 200)->max()->avg()->sum();
    Carbon::setTestNow('2000-01-01 12:59:10');
    Pulse::record('slow_request', 'GET /foo', 200)->max()->avg()->sum();
    Pulse::record('slow_request', 'GET /bar', 400)->max()->avg()->sum();
    Carbon::setTestNow('2000-01-01 12:59:20');
    Pulse::record('slow_request', 'GET /foo', 300)->max()->avg()->sum();
    Pulse::record('slow_request', 'GET /bar', 600)->max()->avg()->sum();

    Pulse::store(app(StorageIngest::class));

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregate('slow_request', 'sum', CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'GET /bar', 'sum' => 2400],
        (object) ['key' => 'GET /foo', 'sum' => 1200],
    ]);

    $results = Pulse::aggregate('slow_request', ['sum', 'count'], CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'GET /bar', 'sum' => 2400, 'count' => 6],
        (object) ['key' => 'GET /foo', 'sum' => 1200, 'count' => 6],
    ]);

    $results = Pulse::aggregate('slow_request', ['sum', 'max', 'avg', 'count'], CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'GET /bar', 'sum' => 2400, 'max' => 600, 'avg' => 400, 'count' => 6],
        (object) ['key' => 'GET /foo', 'sum' => 1200, 'max' => 300, 'avg' => 200, 'count' => 6],
    ]);
});

test('one aggregate for multiple types, per key', function () {
    /*
    | key      | cache_hit | cache_miss |
    |----------|-----------|------------|
    | flight:* | 16        | 8          |
    | user:*   | 4         | 2          |
    */

    // Add entries outside of the window
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'user:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Pulse::record('cache_miss', 'user:*')->sum();

    // Add entries to the "tail"
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Pulse::record('cache_hit', 'user:*')->sum();
    Pulse::record('cache_hit', 'user:*')->sum();
    Pulse::record('cache_miss', 'user:*')->sum();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 12:59:59');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Pulse::record('cache_hit', 'user:*')->sum();
    Pulse::record('cache_hit', 'user:*')->sum();
    Pulse::record('cache_miss', 'user:*')->sum();

    Pulse::store(app(StorageIngest::class));

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregateTypes(['cache_hit', 'cache_miss'], 'sum', CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'flight:*', 'cache_hit' => 8, 'cache_miss' => 6],
        (object) ['key' => 'user:*', 'cache_hit' => 4, 'cache_miss' => 2],
    ]);
});

// multiple aggregates for multiple types?!

test('one aggregate for multiple types, totals', function () {
    /*
    | type       | sum |
    |------------|-----|
    | cache_hit  | 12  |
    | cache_miss | 6   |
    */

    // Add entries outside of the window
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();

    // Add entries to the "tail"
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Carbon::setTestNow('2000-01-01 12:00:02');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Carbon::setTestNow('2000-01-01 12:00:03');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 12:59:00');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Carbon::setTestNow('2000-01-01 12:59:10');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();
    Carbon::setTestNow('2000-01-01 12:59:20');
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_hit', 'flight:*')->sum();
    Pulse::record('cache_miss', 'flight:*')->sum();

    Pulse::store(app(StorageIngest::class));

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregateTotal(['cache_hit', 'cache_miss'], 'sum', CarbonInterval::hour());

    expect($results->all())->toEqual([
        'cache_hit' => 12,
        'cache_miss' => 6,
    ]);
});

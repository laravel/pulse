<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;

test('one or more aggregates for a single type', function () {
    /*
    | key      | max | avg | count |
    |----------|-----|-----|-------|
    | GET /bar | 600 | 400 | 6     |
    | GET /foo | 300 | 200 | 6     |
    */

    // Add entries outside of the window
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_request', 'GET /foo', 100)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 200)->max()->avg()->count();

    // Add entries to the "tail"
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_request', 'GET /foo', 100)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 200)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 300)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 400)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 200)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 400)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 600)->max()->avg()->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 12:59:00');
    Pulse::record('slow_request', 'GET /foo', 100)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 200)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 300)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 400)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 200)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 400)->max()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 600)->max()->avg()->count();

    Pulse::store();

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregate('slow_request', 'count', CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'GET /foo', 'count' => 8],
        (object) ['key' => 'GET /bar', 'count' => 6],
    ]);

    $results = Pulse::aggregate('slow_request', ['max', 'avg', 'count'], CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'GET /bar', 'max' => 600, 'avg' => 400, 'count' => 6],
        (object) ['key' => 'GET /foo', 'max' => 400, 'avg' => 250, 'count' => 8],
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
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'user:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Pulse::record('cache_miss', 'user:*')->count();

    // Add entries to the "tail"
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Pulse::record('cache_hit', 'user:*')->count();
    Pulse::record('cache_hit', 'user:*')->count();
    Pulse::record('cache_miss', 'user:*')->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 12:59:59');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Pulse::record('cache_hit', 'user:*')->count();
    Pulse::record('cache_hit', 'user:*')->count();
    Pulse::record('cache_miss', 'user:*')->count();

    Pulse::store();

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregateTypes(['cache_hit', 'cache_miss'], 'count', CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'flight:*', 'cache_hit' => 8, 'cache_miss' => 6],
        (object) ['key' => 'user:*', 'cache_hit' => 4, 'cache_miss' => 2],
    ]);
});

test('one aggregate for multiple types, totals', function () {
    /*
    | type       | count |
    |------------|-------|
    | cache_hit  | 12    |
    | cache_miss | 6     |
    */

    // Add entries outside of the window
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();

    // Add entries to the "tail"
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Carbon::setTestNow('2000-01-01 12:00:02');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Carbon::setTestNow('2000-01-01 12:00:03');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 12:59:00');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Carbon::setTestNow('2000-01-01 12:59:10');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();
    Carbon::setTestNow('2000-01-01 12:59:20');
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_hit', 'flight:*')->count();
    Pulse::record('cache_miss', 'flight:*')->count();

    Pulse::store();

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregateTotal(['cache_hit', 'cache_miss'], 'count', CarbonInterval::hour());

    expect($results->all())->toEqual([
        'cache_hit' => 12,
        'cache_miss' => 6,
    ]);
});

<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;

it('combines duplicate count aggregates before upserting', function () {
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1')->count();
    Pulse::record('type', 'key1')->count();
    Pulse::record('type', 'key1')->count();
    Pulse::record('type', 'key2')->count();
    Pulse::store();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('pulse_entries');
    expect($queries[1]->sql)->toContain('pulse_aggregates');
    expect($queries[0]->bindings)->toHaveCount(4 * 4); // 4 entries, 4 columns each
    expect($queries[1]->bindings)->toHaveCount(2 * 6 * 4); // 2 entries, 6 columns each, 4 periods

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->orderBy('key')->pluck('value', 'key'));
    expect($aggregates['key1'])->toEqual(3);
    expect($aggregates['key2'])->toEqual(1);
});

it('combines duplicate max aggregates before upserting', function () {
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1', 100)->max();
    Pulse::record('type', 'key1', 300)->max();
    Pulse::record('type', 'key1', 200)->max();
    Pulse::record('type', 'key2', 100)->max();
    Pulse::store();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('pulse_entries');
    expect($queries[1]->sql)->toContain('pulse_aggregates');
    expect($queries[0]->bindings)->toHaveCount(4 * 4); // 4 entries, 4 columns each
    expect($queries[1]->bindings)->toHaveCount(2 * 6 * 4); // 2 entries, 6 columns each, 4 periods

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->orderBy('key')->pluck('value', 'key'));
    expect($aggregates['key1'])->toEqual(300);
    expect($aggregates['key2'])->toEqual(100);
});

it('combines duplicate average aggregates before upserting', function () {
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1', 100)->avg();
    Pulse::record('type', 'key1', 300)->avg();
    Pulse::record('type', 'key1', 200)->avg();
    Pulse::record('type', 'key2', 100)->avg();
    Pulse::store();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('pulse_entries');
    expect($queries[1]->sql)->toContain('pulse_aggregates');
    expect($queries[0]->bindings)->toHaveCount(4 * 4); // 4 entries, 4 columns each
    expect($queries[1]->bindings)->toHaveCount(2 * 7 * 4); // 2 entries, 7 columns each, 4 periods

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->orderBy('key')->get())->keyBy('key');
    expect($aggregates['key1']->value)->toEqual(200);
    expect($aggregates['key2']->value)->toEqual(100);
    expect($aggregates['key1']->count)->toEqual(3);
    expect($aggregates['key2']->count)->toEqual(1);

    Pulse::record('type', 'key1', 400)->avg();
    Pulse::record('type', 'key1', 400)->avg();
    Pulse::record('type', 'key1', 400)->avg();
    Pulse::store();
    $aggregate = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->where('key', 'key1')->first());
    expect($aggregate->value)->toEqual(250);
    expect($aggregate->count)->toEqual(6);
});

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

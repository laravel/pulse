<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;

test('aggregation', function () {
    Pulse::record('type', 'key1', 200)->count()->min()->max()->sum()->avg();
    Pulse::record('type', 'key1', 100)->count()->min()->max()->sum()->avg();
    Pulse::record('type', 'key2', 400)->count()->min()->max()->sum()->avg();
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->orderBy('id')->get());
    expect($entries)->toHaveCount(3);
    expect($entries[0])->toHaveProperties(['type' => 'type', 'key' => 'key1', 'value' => 200]);
    expect($entries[1])->toHaveProperties(['type' => 'type', 'key' => 'key1', 'value' => 100]);
    expect($entries[2])->toHaveProperties(['type' => 'type', 'key' => 'key2', 'value' => 400]);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->orderBy('aggregate')->orderBy('key')->get());
    expect($aggregates)->toHaveCount(40); // 2 entries * 5 aggregates * 4 periods
    expect($aggregates[0])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150]);
    expect($aggregates[1])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[2])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2]);
    expect($aggregates[3])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[4])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200]);
    expect($aggregates[5])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[6])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[7])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[8])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[9])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);

    expect($aggregates[10])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150]);
    expect($aggregates[11])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[12])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2]);
    expect($aggregates[13])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[14])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200]);
    expect($aggregates[15])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[16])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[17])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[18])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[19])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);

    expect($aggregates[20])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150]);
    expect($aggregates[21])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[22])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2]);
    expect($aggregates[23])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[24])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200]);
    expect($aggregates[25])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[26])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[27])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[28])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[29])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);

    expect($aggregates[30])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 150]);
    expect($aggregates[31])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[32])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key1', 'value' => 2]);
    expect($aggregates[33])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[34])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key1', 'value' => 200]);
    expect($aggregates[35])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[36])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[37])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[38])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[39])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);

    Pulse::record('type', 'key1', 600)->count()->min()->max()->sum()->avg();
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->orderBy('id')->get());
    expect($entries)->toHaveCount(4);
    expect($entries[0])->toHaveProperties(['type' => 'type', 'key' => 'key1', 'value' => 200]);
    expect($entries[1])->toHaveProperties(['type' => 'type', 'key' => 'key1', 'value' => 100]);
    expect($entries[2])->toHaveProperties(['type' => 'type', 'key' => 'key2', 'value' => 400]);
    expect($entries[3])->toHaveProperties(['type' => 'type', 'key' => 'key1', 'value' => 600]);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->orderBy('aggregate')->orderBy('key')->get());
    expect($aggregates)->toHaveCount(40); // 2 entries * 5 aggregates * 4 periods
    expect($aggregates[0])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[1])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[2])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3]);
    expect($aggregates[3])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[4])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600]);
    expect($aggregates[5])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[6])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[7])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[8])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900]);
    expect($aggregates[9])->toHaveProperties(['type' => 'type', 'period' => 60, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);

    expect($aggregates[10])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[11])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[12])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3]);
    expect($aggregates[13])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[14])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600]);
    expect($aggregates[15])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[16])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[17])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[18])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900]);
    expect($aggregates[19])->toHaveProperties(['type' => 'type', 'period' => 360, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);

    expect($aggregates[20])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[21])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[22])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3]);
    expect($aggregates[23])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[24])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600]);
    expect($aggregates[25])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[26])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[27])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[28])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900]);
    expect($aggregates[29])->toHaveProperties(['type' => 'type', 'period' => 1440, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);

    expect($aggregates[30])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key1', 'value' => 300]);
    expect($aggregates[31])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'avg', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[32])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key1', 'value' => 3]);
    expect($aggregates[33])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'count', 'key' => 'key2', 'value' => 1]);
    expect($aggregates[34])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key1', 'value' => 600]);
    expect($aggregates[35])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'max', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[36])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key1', 'value' => 100]);
    expect($aggregates[37])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'min', 'key' => 'key2', 'value' => 400]);
    expect($aggregates[38])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key1', 'value' => 900]);
    expect($aggregates[39])->toHaveProperties(['type' => 'type', 'period' => 10080, 'aggregate' => 'sum', 'key' => 'key2', 'value' => 400]);
});

it('combines duplicate count aggregates before upserting', function () {
    Config::set('pulse.ingest.trim.lottery', [0, 1]);
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1')->count();
    Pulse::record('type', 'key1')->count();
    Pulse::record('type', 'key1')->count();
    Pulse::record('type', 'key2')->count();
    Pulse::ingest();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('pulse_entries');
    expect($queries[1]->sql)->toContain('pulse_aggregates');
    expect($queries[0]->bindings)->toHaveCount(4 * 4); // 4 entries, 4 columns each
    expect($queries[1]->bindings)->toHaveCount(2 * 6 * 4); // 2 entries, 6 columns each, 4 periods

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->orderBy('key')->pluck('value', 'key'));
    expect($aggregates['key1'])->toEqual(3);
    expect($aggregates['key2'])->toEqual(1);
});

it('combines duplicate min aggregates before upserting', function () {
    Config::set('pulse.ingest.trim.lottery', [0, 1]);
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1', 200)->min();
    Pulse::record('type', 'key1', 100)->min();
    Pulse::record('type', 'key1', 300)->min();
    Pulse::record('type', 'key2', 100)->min();
    Pulse::ingest();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('pulse_entries');
    expect($queries[1]->sql)->toContain('pulse_aggregates');
    expect($queries[0]->bindings)->toHaveCount(4 * 4); // 4 entries, 4 columns each
    expect($queries[1]->bindings)->toHaveCount(2 * 6 * 4); // 2 entries, 6 columns each, 4 periods

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->orderBy('key')->pluck('value', 'key'));
    expect($aggregates['key1'])->toEqual(100);
    expect($aggregates['key2'])->toEqual(100);
});

it('combines duplicate max aggregates before upserting', function () {
    Config::set('pulse.ingest.trim.lottery', [0, 1]);
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1', 100)->max();
    Pulse::record('type', 'key1', 300)->max();
    Pulse::record('type', 'key1', 200)->max();
    Pulse::record('type', 'key2', 100)->max();
    Pulse::ingest();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('pulse_entries');
    expect($queries[1]->sql)->toContain('pulse_aggregates');
    expect($queries[0]->bindings)->toHaveCount(4 * 4); // 4 entries, 4 columns each
    expect($queries[1]->bindings)->toHaveCount(2 * 6 * 4); // 2 entries, 6 columns each, 4 periods

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->orderBy('key')->pluck('value', 'key'));
    expect($aggregates['key1'])->toEqual(300);
    expect($aggregates['key2'])->toEqual(100);
});

it('combines duplicate sum aggregates before upserting', function () {
    Config::set('pulse.ingest.trim.lottery', [0, 1]);
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1', 100)->sum();
    Pulse::record('type', 'key1', 300)->sum();
    Pulse::record('type', 'key1', 200)->sum();
    Pulse::record('type', 'key2', 100)->sum();
    Pulse::ingest();

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toContain('pulse_entries');
    expect($queries[1]->sql)->toContain('pulse_aggregates');
    expect($queries[0]->bindings)->toHaveCount(4 * 4); // 4 entries, 4 columns each
    expect($queries[1]->bindings)->toHaveCount(2 * 6 * 4); // 2 entries, 6 columns each, 4 periods

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->orderBy('key')->pluck('value', 'key'));
    expect($aggregates['key1'])->toEqual(600);
    expect($aggregates['key2'])->toEqual(100);
});

it('combines duplicate average aggregates before upserting', function () {
    Config::set('pulse.ingest.trim.lottery', [0, 1]);
    $queries = collect();
    DB::listen(fn ($query) => $queries[] = $query);

    Pulse::record('type', 'key1', 100)->avg();
    Pulse::record('type', 'key1', 300)->avg();
    Pulse::record('type', 'key1', 200)->avg();
    Pulse::record('type', 'key2', 100)->avg();
    Pulse::ingest();

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
    Pulse::ingest();
    $aggregate = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->where('key', 'key1')->first());
    expect($aggregate->count)->toEqual(6);
    expect($aggregate->value)->toEqual(300);
});

test('one or more aggregates for a single type', function () {
    /*
    | key      | min | max | sum  | avg | count |
    |----------|-----|-----|------|-----|-------|
    | GET /bar | 200 | 600 | 2400 | 400 | 6     |
    | GET /foo | 100 | 300 | 2000 | 200 | 6     |
    */

    // Add entries outside of the window
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_request', 'GET /foo', 100)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 200)->min()->max()->sum()->avg()->count();

    // Add entries to the "tail"
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_request', 'GET /foo', 100)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 200)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 300)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 400)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 200)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 400)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 600)->min()->max()->sum()->avg()->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 12:59:00');
    Pulse::record('slow_request', 'GET /foo', 100)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 200)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 300)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /foo', 400)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 200)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 400)->min()->max()->sum()->avg()->count();
    Pulse::record('slow_request', 'GET /bar', 600)->min()->max()->sum()->avg()->count();

    Pulse::ingest();

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregate('slow_request', 'count', CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'GET /foo', 'count' => 8],
        (object) ['key' => 'GET /bar', 'count' => 6],
    ]);

    $results = Pulse::aggregate('slow_request', ['min', 'max', 'sum', 'avg', 'count'], CarbonInterval::hour());

    expect($results->all())->toEqual([
        (object) ['key' => 'GET /bar', 'min' => 200, 'max' => 600, 'sum' => 2400, 'avg' => 400, 'count' => 6],
        (object) ['key' => 'GET /foo', 'min' => 100, 'max' => 400, 'sum' => 2000, 'avg' => 250, 'count' => 8],
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

    Pulse::ingest();

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

    Pulse::ingest();

    Carbon::setTestNow('2000-01-01 13:00:00');

    $results = Pulse::aggregateTotal(['cache_hit', 'cache_miss'], 'count', CarbonInterval::hour());

    expect($results->all())->toEqual([
        'cache_hit' => 12,
        'cache_miss' => 6,
    ]);
});

<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowQueries;

it('ingests queries', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::connection()->statement('select * from users');

    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp - 5,
        'type' => 'slow_query',
        'value' => 5000,
    ]);
    $key = json_decode($entries[0]->key);
    expect($key[0])->toBe('select * from users');
    expect($key[1])->not->toBeNull();
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->orderBy('aggregate')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) (floor((now()->timestamp - 5) / 60) * 60),
        'period' => 60,
        'type' => 'slow_query',
        'aggregate' => 'count',
        'value' => 1,
    ]);
    $key = json_decode($aggregates[0]->key);
    expect($key[0])->toBe('select * from users');
    expect($key[1])->not->toBeNull();
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) (floor((now()->timestamp - 5) / 60) * 60),
        'period' => 60,
        'type' => 'slow_query',
        'aggregate' => 'max',
        'value' => 5000,
    ]);
    $key = json_decode($aggregates[1]->key);
    expect($key[0])->toBe('select * from users');
    expect($key[1])->not->toBeNull();
});

it('can disable capturing the location', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.location', false);
    Carbon::setTestNow('2000-01-02 03:04:05');
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::connection()->statement('select * from users');
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp - 5,
        'type' => 'slow_query',
        'value' => 5000,
    ]);
    $key = json_decode($entries[0]->key);
    expect($key[0])->toBe('select * from users');
    expect($key[1])->toBeNull();
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->orderBy('aggregate')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) (floor((now()->timestamp - 5) / 60) * 60),
        'period' => 60,
        'type' => 'slow_query',
        'aggregate' => 'count',
        'value' => 1,
    ]);
    $key = json_decode($aggregates[0]->key);
    expect($key[0])->toBe('select * from users');
    expect($key[1])->toBeNull();
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) (floor((now()->timestamp - 5) / 60) * 60),
        'period' => 60,
        'type' => 'slow_query',
        'aggregate' => 'max',
        'value' => 5000,
    ]);
    $key = json_decode($aggregates[1]->key);
    expect($key[0])->toBe('select * from users');
    expect($key[1])->toBeNull();
});

it('does not ingest queries under the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 4999;
    });

    DB::table('users')->count();
    Pulse::ingest();

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
});

it('ingests queries equal to the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::table('users')->count();
    Pulse::ingest();

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(1));
});

it('ingests queries over the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5001;
    });

    DB::table('users')->count();
    Pulse::ingest();

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(1));
});

it('can ignore queries', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowQueries::class.'.ignore', [
        '/(["`])pulse_[\w]+?\1/', // Pulse tables
    ]);

    expect(Pulse::ingest())->toBe(0);
});

it('can sample', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowQueries::class.'.sample_rate', 0.1);

    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();

    expect(Pulse::ingest())->toEqualWithDelta(1, 4);
});

it('can sample at zero', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowQueries::class.'.sample_rate', 0);

    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();

    expect(Pulse::ingest())->toBe(0);
});

it('can sample at one', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowQueries::class.'.sample_rate', 1);

    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();
    DB::table('users')->count();

    expect(Pulse::ingest())->toBe(10);
});

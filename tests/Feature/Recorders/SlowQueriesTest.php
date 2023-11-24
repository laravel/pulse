<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowQueries;

it('ingests queries', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::connection()->statement('select * from users');

    expect(Pulse::entries())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));

    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp - 5,
        'type' => 'slow_query',
        'value' => 5000,
    ]);
    expect($entries[0]->key)->toStartWith('select * from users::'.__FILE__.':');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) floor((now()->timestamp - 5) / 60) * 60,
        'period' => 60,
        'type' => 'slow_query:count',
        'value' => 1,
    ]);
    expect($aggregates[0]->key)->toStartWith('select * from users::'.__FILE__.':');
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) floor((now()->timestamp - 5) / 60) * 60,
        'period' => 60,
        'type' => 'slow_query:max',
        'value' => 5000,
    ]);
    expect($aggregates[1]->key)->toStartWith('select * from users::'.__FILE__.':');
});

it('can disable capturing the location', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.location', false);
    Carbon::setTestNow('2000-01-02 03:04:05');
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::connection()->statement('select * from users');
    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp - 5,
        'type' => 'slow_query',
        'key' => 'select * from users',
        'value' => 5000,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) floor((now()->timestamp - 5) / 60) * 60,
        'period' => 60,
        'type' => 'slow_query:count',
        'key' => 'select * from users',
        'value' => 1,
    ]);
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) floor((now()->timestamp - 5) / 60) * 60,
        'period' => 60,
        'type' => 'slow_query:max',
        'key' => 'select * from users',
        'value' => 5000,
    ]);
});

it('does not ingest queries under the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 4999;
    });

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
});

it('ingests queries equal to the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(1));
});

it('ingests queries over the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5001;
    });

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(1));
});

it('can ignore queries', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowQueries::class.'.ignore', [
        '/(["`])pulse_[\w]+?\1/', // Pulse tables
    ]);

    DB::table('pulse_entries')->count();

    expect(Pulse::entries())->toHaveCount(0);
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

    expect(count(Pulse::entries()))->toEqualWithDelta(1, 4);

    Pulse::flushEntries();
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

    expect(count(Pulse::entries()))->toBe(0);

    Pulse::flushEntries();
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

    expect(count(Pulse::entries()))->toBe(10);

    Pulse::flushEntries();
});

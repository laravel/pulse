<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Facades\Pulse;

beforeEach(function () {
    Pulse::handleExceptionsUsing(fn ($e) => throw $e);

    Pulse::ignore(fn () => Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    }));
});

it('ingests queries', function () {
    Config::set('pulse.slow_query_threshold', 0);

    expect(Pulse::queue())->toHaveCount(0);

    DB::table('users')->count();
    expect(Pulse::queue())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(0));

    Pulse::store();
    expect(Pulse::queue())->toHaveCount(0);
    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(1));
});

it('does not ingest queries under the slow query threshold', function () {
    Config::set('pulse.slow_query_threshold', 1000);
    $listeners = Event::getRawListeners()[QueryExecuted::class];
    Event::forget(QueryExecuted::class);
    collect([
        function (QueryExecuted $event) {
            $event->time = 999;
        },
        ...$listeners,
    ])->each(fn ($listener) => Event::listen(QueryExecuted::class, $listener));

    DB::table('users')->count();
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(0));
});

it('ingests queries equal to the slow query threshold', function () {
    Config::set('pulse.slow_query_threshold', 1000);
    $listeners = Event::getRawListeners()[QueryExecuted::class];
    Event::forget(QueryExecuted::class);
    collect([
        function (QueryExecuted $event) {
            $event->time = 1000;
        },
        ...$listeners,
    ])->each(fn ($listener) => Event::listen(QueryExecuted::class, $listener));

    DB::table('users')->count();
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(1));
});

it('ingests queries over the slow query threshold', function () {
    Config::set('pulse.slow_query_threshold', 1000);
    $listeners = Event::getRawListeners()[QueryExecuted::class];
    Event::forget(QueryExecuted::class);
    collect([
        function (QueryExecuted $event) {
            $event->time = 1001;
        },
        ...$listeners,
    ])->each(fn ($listener) => Event::listen(QueryExecuted::class, $listener));

    DB::table('users')->count();
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(1));
});

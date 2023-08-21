<?php

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Facades\Pulse;

beforeEach(function () {
    Pulse::ignore(fn () => Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    }));
});

it('ingests queries', function () {
    Config::set('pulse.slow_query_threshold', 0);
    Carbon::setTestNow('2020-01-02 03:04:05');
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::table('users')->count();
    expect(Pulse::queue())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(0));

    Pulse::store();
    $queries = Pulse::ignore(fn () => DB::table('pulse_queries')->get());

    expect(Pulse::queue())->toHaveCount(0);
    expect($queries)->toHaveCount(1);
    expect((array) $queries->first())->toEqual([
        'date' => '2020-01-02 03:04:00',
        'user_id' => null,
        'sql' => 'select count(*) as aggregate from "users"',
        'duration' => 5000,
    ]);
});

it('does not ingest queries under the slow query threshold', function () {
    Config::set('pulse.slow_query_threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 4999;
    });

    DB::table('users')->count();
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(0));
});

it('ingests queries equal to the slow query threshold', function () {
    Config::set('pulse.slow_query_threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::table('users')->count();
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(1));
});

it('ingests queries over the slow query threshold', function () {
    Config::set('pulse.slow_query_threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5001;
    });

    DB::table('users')->count();
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_queries')->count())->toBe(1));
});

it('captures the authenticated user', function () {
    Config::set('pulse.slow_query_threshold', 0);

    Auth::setUser(User::make(['id' => '567']));
    DB::table('users')->count();
    Pulse::store();

    $queries = Pulse::ignore(fn () => DB::table('pulse_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login after the query is made', function () {
    Config::set('pulse.slow_query_threshold', 0);
    Config::set('auth.guards.db', ['driver' => 'db']);

    Auth::forgetUser();
    DB::table('users')->count();
    Auth::setUser(User::make(['id' => '567']));
    Pulse::store();

    $queries = Pulse::ignore(fn () => DB::table('pulse_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout after the query is made', function () {
    Config::set('pulse.slow_query_threshold', 0);
    Config::set('auth.guards.db', ['driver' => 'db']);

    Auth::setUser(User::make(['id' => '567']));
    DB::table('users')->count();
    Auth::forgetUser();
    Pulse::store();

    $queries = Pulse::ignore(fn () => DB::table('pulse_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe('567');
});

it('does not trigger an inifite loop when retriving the authenticated user from the database', function () {
    Config::set('pulse.slow_query_threshold', 0);
    Config::set('auth.guards.db', ['driver' => 'db']);
    Auth::extend('db', fn () => new class implements Guard {
        use GuardHelpers;

        public function validate(array $credentials = [])
        {
            return true;
        }

        public function user()
        {
            static $count = 0;

            if (++$count > 5) {
                throw new RuntimeException('Infinite loop detected.');
            }

            return User::first();
        }
    })->shouldUse('db');

    DB::table('users')->count();
    Pulse::store();

    $queries = Pulse::ignore(fn () => DB::table('pulse_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe(null);
});

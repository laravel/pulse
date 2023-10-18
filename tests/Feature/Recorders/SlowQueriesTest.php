<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Pulse as PulseInstance;
use Laravel\Pulse\Recorders\SlowQueries;

it('ingests queries', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::connection()->statement('select * from users');

    expect(Pulse::entries())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_slow_queries')->count())->toBe(0));

    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $queries = Pulse::ignore(fn () => DB::table('pulse_slow_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:00',
        'user_id' => null,
        'sql' => 'select * from users',
        'duration' => 5000,
    ]);
});

it('does not ingest queries under the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 4999;
    });

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_slow_queries')->count())->toBe(0));
});

it('ingests queries equal to the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5000;
    });

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_slow_queries')->count())->toBe(1));
});

it('ingests queries over the slow query threshold', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 5000);
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 5001;
    });

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_slow_queries')->count())->toBe(1));
});

it('captures the authenticated user', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Auth::login(User::make(['id' => '567']));

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    $queries = Pulse::ignore(fn () => DB::table('pulse_slow_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login after the query', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    DB::table('users')->count();
    Auth::login(User::make(['id' => '567']));
    Pulse::store(app(Ingest::class));

    $queries = Pulse::ignore(fn () => DB::table('pulse_slow_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout after the query', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Auth::login(User::make(['id' => '567']));

    DB::table('users')->count();
    Auth::logout();
    Pulse::store(app(Ingest::class));

    $queries = Pulse::ignore(fn () => DB::table('pulse_slow_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe('567');
});

it('does not trigger an inifite loop when retriving the authenticated user from the database', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Config::set('auth.guards.db', ['driver' => 'db']);
    Auth::extend('db', fn () => new class implements Guard
    {
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
    Pulse::store(app(Ingest::class));

    $queries = Pulse::ignore(fn () => DB::table('pulse_slow_queries')->get());
    expect($queries)->toHaveCount(1);
    expect($queries[0]->user_id)->toBe(null);
});

it('quietly fails if an exception is thrown while preparing the entry payload', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    App::forgetInstance(PulseInstance::class);
    Facade::clearResolvedInstance(PulseInstance::class);
    App::when(PulseInstance::class)
        ->needs(AuthManager::class)
        ->give(fn (Application $app) => new class($app) extends AuthManager
        {
            public function hasUser()
            {
                throw new RuntimeException('Error checking for user.');
            }
        });

    DB::table('users')->count();
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_slow_queries')->count())->toBe(0));
});

it('handles multiple users being logged in', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Pulse::withUser(null, fn () => DB::table('users')->count());
    Auth::login(User::make(['id' => '567']));
    DB::table('users')->count();
    Auth::login(User::make(['id' => '789']));
    DB::table('users')->count();

    Pulse::store(app(Ingest::class));

    $queries = Pulse::ignore(fn () => DB::table('pulse_slow_queries')->get());
    expect($queries)->toHaveCount(3);
    expect($queries[0]->user_id)->toBe(null);
    expect($queries[1]->user_id)->toBe('567');
    expect($queries[2]->user_id)->toBe('789');
});

it('can ignore queries', function () {
    Config::set('pulse.recorders.'.SlowQueries::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowQueries::class.'.ignore', [
        '/(["`])pulse_[\w]+?\1/', // Pulse tables
    ]);

    DB::table('pulse_slow_queries')->count();

    expect(Pulse::entries())->toHaveCount(0);
});

it('can sample', function () {
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

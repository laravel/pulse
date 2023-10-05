<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
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

it('ingests exceptions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    report($exception = new RuntimeException('Expected exception.'));

    expect(Pulse::entries())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_exceptions')->count())->toBe(0));

    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect((array) $exceptions[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'class' => 'RuntimeException',
        'location' => __FILE__.':'.$exception->getLine(),
    ]);
});

it('captures the authenticated user', function () {
    Auth::login(User::make(['id' => '567']));

    report($exception = new RuntimeException('Expected exception.'));
    Pulse::store(app(Ingest::class));

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login after the exception is reported', function () {
    report($exception = new RuntimeException('Expected exception.'));
    Auth::login(User::make(['id' => '567']));
    Pulse::store(app(Ingest::class));

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout after the exception is reported', function () {
    Auth::login(User::make(['id' => '567']));

    report($exception = new RuntimeException('Expected exception.'));
    Auth::logout();
    Pulse::store(app(Ingest::class));

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->user_id)->toBe('567');
});

it('does not trigger an inifite loop when retriving the authenticated user from the database', function () {
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

    report($exception = new RuntimeException('Expected exception.'));
    Pulse::store(app(Ingest::class));

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->user_id)->toBe(null);
});

it('quietly fails if an exception is thrown while preparing the entry payload', function () {
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

    report($exception = new RuntimeException('Expected exception.'));
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_exceptions')->count())->toBe(0));
});

it('handles multiple users being logged in', function () {
    Pulse::withUser(null, fn () => report($exception = new RuntimeException('Expected exception.')));
    Auth::login(User::make(['id' => '567']));
    report($exception = new RuntimeException('Expected exception.'));
    Auth::login(User::make(['id' => '789']));
    report($exception = new RuntimeException('Expected exception.'));
    Pulse::store(app(Ingest::class));

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(3);
    expect($exceptions[0]->user_id)->toBe(null);
    expect($exceptions[1]->user_id)->toBe('567');
    expect($exceptions[2]->user_id)->toBe('789');
});

it('can manually report exceptions', function () {
    Carbon::setTestNow('2000-01-01 00:00:00');

    Pulse::report(new MyReportedException('Hello, Pulse!'));
    Pulse::store(app(Ingest::class));

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());

    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->date)->toBe('2000-01-01 00:00:00');
    expect($exceptions[0]->class)->toBe('MyReportedException');
});

class MyReportedException extends Exception
{
    //
}

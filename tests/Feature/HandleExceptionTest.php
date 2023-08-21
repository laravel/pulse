<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Handlers\HandleException;
use Laravel\Pulse\Pulse as PulseInstance;

beforeEach(function () {
    Pulse::ignore(fn () => Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    }));
});

it('ingests exceptions', function () {
    Carbon::setTestNow('2020-01-02 03:04:05');

    report($exception = new RuntimeException('Expected exception.'));

    expect(Pulse::queue())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_exceptions')->count())->toBe(0));

    Pulse::store();

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect(Pulse::queue())->toHaveCount(0);
    expect($exceptions)->toHaveCount(1);
    expect((array) $exceptions->first())->toEqual([
        'date' => '2020-01-02 03:04:05',
        'user_id' => null,
        'class' => 'RuntimeException',
        'location' => __FILE__.':'.$exception->getLine(),
    ]);
});

it('captures the authenticated user', function () {
    Auth::setUser(User::make(['id' => '567']));

    report($exception = new RuntimeException('Expected exception.'));
    Pulse::store();

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login after the exception is reported', function () {
    report($exception = new RuntimeException('Expected exception.'));
    Auth::setUser(User::make(['id' => '567']));
    Pulse::store();

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout after the exception is reported', function () {
    Auth::setUser(User::make(['id' => '567']));

    report($exception = new RuntimeException('Expected exception.'));
    Auth::forgetUser();
    Pulse::store();

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
    Pulse::store();

    $exceptions = Pulse::ignore(fn () => DB::table('pulse_exceptions')->get());
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->user_id)->toBe(null);
});

it('quietly fails if an exception is thrown while preparing the entry payload', function () {
    App::forgetInstance(PulseInstance::class);
    Facade::clearResolvedInstance(PulseInstance::class);
    App::when(HandleException::class)
        ->needs(AuthManager::class)
        ->give(fn (Application $app) => new class($app) extends AuthManager
        {
            public function hasUser()
            {
                throw new RuntimeException('Error checking for user.');
            }
        });

    report($exception = new RuntimeException('Expected exception.'));
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_exceptions')->count())->toBe(0));
});

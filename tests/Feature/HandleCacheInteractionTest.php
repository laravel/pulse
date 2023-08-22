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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Handlers\HandleCacheInteraction;
use Laravel\Pulse\Pulse as PulseInstance;

beforeEach(function () {
    Pulse::ignore(fn () => Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    }));
});

it('ingests cache interactions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::get('cache-key');

    expect(Pulse::queue())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_cache_hits')->count())->toBe(0));

    Pulse::store();

    expect(Pulse::queue())->toHaveCount(0);
    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(1);
    expect((array) $cacheHits[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'cache-key',
        'hit' => 0,
    ]);
});

it('ignores any internal illuminate cache interactions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::get('illuminate:');
    Pulse::store();

    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(0);
});

it('ignores any internal pulse cache interactions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::get('laravel:pulse');
    Pulse::store();

    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(0);
});

it('captures hits and misses', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Cache::put('hit', 123);

    Cache::get('hit');
    Cache::get('miss');
    Pulse::store();

    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(2);
    expect((array) $cacheHits[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'hit',
        'hit' => 1,
    ]);
    expect((array) $cacheHits[1])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'miss',
        'hit' => 0,
    ]);
});

it('captures the authenticated user', function () {
    Auth::setUser(User::make(['id' => '567']));

    Cache::get('cache-key');
    Pulse::store();

    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(1);
    expect($cacheHits[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login after the interaction', function () {
    Cache::get('cache-key');
    Auth::setUser(User::make(['id' => '567']));
    Pulse::store();

    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(1);
    expect($cacheHits[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout after the interaction', function () {
    Auth::setUser(User::make(['id' => '567']));

    Cache::get('cache-key');
    Auth::logout();
    Pulse::store();

    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(1);
    expect($cacheHits[0]->user_id)->toBe('567');
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

    Cache::get('cache-key');
    Pulse::store();

    $cacheHits = Pulse::ignore(fn () => DB::table('pulse_cache_hits')->get());
    expect($cacheHits)->toHaveCount(1);
    expect($cacheHits[0]->user_id)->toBe(null);
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

    Cache::get('cache-key');
    Pulse::store();

    Pulse::ignore(fn () => expect(DB::table('pulse_cache_hits')->count())->toBe(0));
});

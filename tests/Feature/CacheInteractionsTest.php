<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Pulse as PulseInstance;

it('ingests cache interactions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::get('cache-key');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect($interactions[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'cache-key',
        'hit' => 0,
    ]);
});

it('ignores internal illuminate cache interactions', function () {
    Cache::get('illuminate:');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(0);
});

it('ignores internal pulse cache interactions', function () {
    Cache::get('laravel:pulse:foo');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(0);
});

it('ingests hits', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::put('hit', 123);
    Cache::get('hit');
    Cache::get('miss');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(2);
    expect($interactions[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'hit',
        'hit' => 1,
    ]);
    expect($interactions[1])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'miss',
        'hit' => 0,
    ]);
});

it('captures the authenticated user', function () {
    Auth::login(User::make(['id' => '567']));

    Cache::get('cache-key');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect($interactions[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login after the interaction', function () {
    Cache::get('cache-key');
    Auth::login(User::make(['id' => '567']));
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect($interactions[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout after the interaction', function () {
    Auth::login(User::make(['id' => '567']));

    Cache::get('cache-key');
    Auth::logout();
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect($interactions[0]->user_id)->toBe('567');
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
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect($interactions[0]->user_id)->toBe(null);
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
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_cache_interactions')->count())->toBe(0));
});

it('handles multiple users being logged in', function () {
    Pulse::withUser(null, fn () => Cache::get('cache-key'));
    Auth::login(User::make(['id' => '567']));
    Cache::get('cache-key');
    Auth::login(User::make(['id' => '789']));
    Cache::get('cache-key');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(3);
    expect($interactions[0]->user_id)->toBe(null);
    expect($interactions[1]->user_id)->toBe('567');
    expect($interactions[2]->user_id)->toBe('789');
});

it('stores the original keys by default', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::get('users:1234:profile');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect((array) $interactions[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'users:1234:profile',
        'hit' => 0,
    ]);
});

it('can normalize cache keys', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.cache_keys', [
        '/users:\d+:profile/' => 'users:{user}:profile',
    ]);
    Cache::get('users:1234:profile');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect((array) $interactions[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'users:{user}:profile',
        'hit' => 0,
    ]);
});

it('can use back references in normalized cache keys', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.cache_keys', [
        '/^([^:]+):([^:]+):baz/' => '\2:\1',
    ]);
    Cache::get('foo:bar:baz');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect((array) $interactions[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'bar:foo',
        'hit' => 0,
    ]);
});

it('uses the original key if no matching pattern is found', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.cache_keys', [
        '/\d/' => 'foo',
    ]);
    Cache::get('actual-key');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect((array) $interactions[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'actual-key',
        'hit' => 0,
    ]);
});

it('can provide regex flags in normalization key', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.cache_keys', [
        '/foo/i' => 'lowercase-key',
        '/FOO/i' => 'uppercase-key',
    ]);
    Cache::get('FOO');
    Pulse::store(app(Ingest::class));

    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions)->toHaveCount(1);
    expect((array) $interactions[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'key' => 'lowercase-key',
        'hit' => 0,
    ]);
});

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
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Pulse as PulseInstance;
use Laravel\Pulse\Recorders\Requests;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('ingests slow unauthenticated requests', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Route::get('users', fn () => []);

    get('users');

    expect(Pulse::entries())->toHaveCount(0);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_request',
        'key' => 'GET /users',
        'value' => 0,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'slow_request:count',
        'key' => 'GET /users',
        'value' => 1,
    ]);
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'slow_request:max',
        'key' => 'GET /users',
        'value' => 0,
    ]);
});

it('ignores unauthenticated requests under the slow endpoint threshold', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', PHP_INT_MAX);
    Route::get('users', fn () => []);

    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(0);
});

it('captures authenticated requests under the slow endpoint threshold', function () {
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']))->get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'user_request',
        'key' => '567',
        'value' => null,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(4);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'user_request:count',
        'key' => '567',
        'value' => 1,
    ]);
});

it('captures the authenticated user if they login during the request', function () {
    Route::post('login', fn () => Auth::login(User::make(['id' => '567'])));

    post('login');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('567');
});

it('captures the authenticated user if they logout during the request', function () {
    Route::post('logout', fn () => Auth::logout());

    actingAs(User::make(['id' => '567']))->post('logout');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->user_id)->toBe('567');
});

it('does not trigger an infinite loop when retrieving the authenticated user from the database', function () {
    Route::get('users', fn () => []);
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

    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->user_id)->toBe(null);
});

it('quietly fails if an exception is thrown while preparing the entry payload', function () {
    Route::get('users', fn () => []);
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

    get('users');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
});

it('can ignore requests', function () {
    Config::set('pulse.recorders.'.Requests::class.'.ignore', [
        '#^/pulse$#', // Pulse dashboard
    ]);

    get('pulse');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
});

it('ignores livewire update requests from an ignored path', function () {
    Route::post('livewire/update', fn () => [])->name('livewire.update');
    Config::set('pulse.recorders.'.Requests::class.'.ignore', [
        '#^/pulse$#', // Pulse dashboard
    ]);

    post('/livewire/update', [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'path' => 'pulse',
                    ],
                ]),
            ],
        ],
    ]);

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
});

it('only records known routes', function () {
    $response = get('some-route-that-does-not-exit');

    $response->assertNotFound();
    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
});

it('handles routes with domains', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', 0);
    Route::domain('{account}.example.com')->get('users', fn () => 'account users');
    Route::get('users', fn () => 'global users');

    get('http://foo.example.com/users')->assertContent('account users');
    get('http://example.com/users')->assertContent('global users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(2);
    expect($entries[0])->toHaveProperty('key', 'GET {account}.example.com/users');
    expect($entries[1])->toHaveProperty('key', 'GET /users');
});

it('can sample', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', 0);
    Config::set('pulse.recorders.'.Requests::class.'.sample_rate', 0.1);
    Route::get('users', fn () => []);

    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect(count($entries))->toEqualWithDelta(1, 4);
});

it('can sample at zero', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', 0);
    Config::set('pulse.recorders.'.Requests::class.'.sample_rate', 0);
    Route::get('users', fn () => []);

    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect(count($entries))->toBe(0);
});

it('can sample at one', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', 0);
    Config::set('pulse.recorders.'.Requests::class.'.sample_rate', 1);
    Route::get('users', fn () => []);

    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');
    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect(count($entries))->toBe(10);
});

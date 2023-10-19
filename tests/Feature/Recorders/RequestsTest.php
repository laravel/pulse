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

it('ingests requests', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Route::get('users', fn () => []);

    get('users');

    expect(Pulse::entries())->toHaveCount(0);
    $requests = Pulse::ignore(fn () => DB::table('pulse_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => 0,
        'route' => 'GET /users',
    ]);
});

it('ingests requests under the slow endpoint threshold', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', PHP_INT_MAX);
    Route::get('users', fn () => []);

    get('users');

    $requests = Pulse::ignore(fn () => DB::table('pulse_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->reached_threshold)->toBe(0);
});

it('ingests requests over the slow endpoint threshold', function () {
    Config::set('pulse.recorders.'.Requests::class.'.threshold', 0);
    Route::get('users', fn () => []);

    get('users');

    $requests = Pulse::ignore(fn () => DB::table('pulse_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->reached_threshold)->toBe(1);
});

it('captures the authenticated user', function () {
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']))->get('users');

    $requests = Pulse::ignore(fn () => DB::table('pulse_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login during the request', function () {
    Route::post('login', fn () => Auth::login(User::make(['id' => '567'])));

    post('login');

    $requests = Pulse::ignore(fn () => DB::table('pulse_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout during the request', function () {
    Route::post('logout', fn () => Auth::logout());

    actingAs(User::make(['id' => '567']))->post('logout');

    $requests = Pulse::ignore(fn () => DB::table('pulse_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe('567');
});

it('does not trigger an inifite loop when retriving the authenticated user from the database', function () {
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

    $requests = Pulse::ignore(fn () => DB::table('pulse_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe(null);
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

    Pulse::ignore(fn () => expect(DB::table('pulse_requests')->count())->toBe(0));
});

it('can ignore requests', function () {
    Config::set('pulse.recorders.'.Requests::class.'.ignore', [
        '#^/pulse$#', // Pulse dashboard
    ]);

    get('pulse');

    Pulse::ignore(fn () => expect(DB::table('pulse_requests')->count())->toBe(0));
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

    Pulse::ignore(fn () => expect(DB::table('pulse_requests')->count())->toBe(0));
});

it('only records known routes', function () {
    $response = get('some-route-that-does-not-exit');

    $response->assertNotFound();
    Pulse::ignore(fn () => expect(DB::table('pulse_requests')->count())->toBe(0));
});

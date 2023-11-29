<?php

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\UserRequests;
use Tests\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('captures authenticated requests', function () {
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']))->get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'user_request',
        'key' => '567',
        'value' => 1,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'user_request',
        aggregate: 'count',
        key: '567',
        value: 1,
    );
});

it('ignores unauthenticated requests', function () {
    Route::get('users', fn () => []);

    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(0);
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
    expect($entries[0]->key)->toBe('567');
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
    expect($entries)->toHaveCount(0);
});

it('can ignore requests', function () {
    Config::set('pulse.recorders.'.UserRequests::class.'.ignore', [
        '#^/users#',
    ]);
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']))->get('users');

    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->count()))->toBe(0);
});

it('ignores livewire update requests from an ignored path', function () {
    Config::set('pulse.recorders.'.UserRequests::class.'.ignore', [
        '#^/users#',
    ]);
    Route::get('users', fn () => []);
    Route::post('livewire/update', fn () => [])->name('livewire.update');

    actingAs(User::make(['id' => '567']))
        ->post('/livewire/update', [
            'components' => [
                [
                    'snapshot' => json_encode([
                        'memo' => [
                            'path' => 'users',
                        ],
                    ]),
                ],
            ],
        ]);

    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->count()))->toBe(0);
});

it('can sample', function () {
    Config::set('pulse.recorders.'.UserRequests::class.'.sample_rate', 0.1);
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']));
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

    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->count()))->toEqualWithDelta(1, 4);
});

it('can sample at zero', function () {
    Config::set('pulse.recorders.'.UserRequests::class.'.sample_rate', 0);
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']));
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

    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->count()))->toBe(0);
});

it('can sample at one', function () {
    Config::set('pulse.recorders.'.UserRequests::class.'.sample_rate', 1);
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']));
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

    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->count()))->toBe(10);
});

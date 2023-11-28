<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowRequests;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowRequests::class);
});

it('renders slow requests', function () {
    Route::get('/users', ['FooController', 'index']);
    Route::get('/users/{user}', fn () => 'users');

    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_request', 'GET /users', 1)->max()->count();
    Pulse::record('slow_request', 'GET /users/{user}', 1)->max()->count();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_request', 'GET /users', 1234)->max()->count();
    Pulse::record('slow_request', 'GET /users', 2468)->max()->count();
    Pulse::record('slow_request', 'GET /users/{user}', 1234)->max()->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('slow_request', 'GET /users', 1000)->max()->count();
    Pulse::record('slow_request', 'GET /users', 1000)->max()->count();
    Pulse::record('slow_request', 'GET /users/{user}', 1000)->max()->count();

    Pulse::store();

    Livewire::test(SlowRequests::class, ['lazy' => false])
        ->assertViewHas('slowRequests', collect([
            (object) ['method' => 'GET', 'uri' => '/users', 'action' => 'FooController@index', 'count' => 4, 'slowest' => 2468],
            (object) ['method' => 'GET', 'uri' => '/users/{user}', 'action' => 'Closure', 'count' => 2, 'slowest' => 1234],
        ]));
});

it('handles routes with domains', function () {
    Route::domain('{account}.example.com')->group(function () {
        Route::get('users', ['AccountUserController', 'index']);
    });
    Route::get('users', ['GlobalUserController', 'index']);

    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_request', 'GET /users', 1)->max()->count();
    Pulse::record('slow_request', 'GET {account}.example.com/users', 1)->max()->count();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_request', 'GET /users', 1234)->max()->count();
    Pulse::record('slow_request', 'GET /users', 2468)->max()->count();
    Pulse::record('slow_request', 'GET {account}.example.com/users', 1234)->max()->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('slow_request', 'GET /users', 1000)->max()->count();
    Pulse::record('slow_request', 'GET /users', 1000)->max()->count();
    Pulse::record('slow_request', 'GET {account}.example.com/users', 1000)->max()->count();

    Pulse::store();

    Livewire::test(SlowRequests::class, ['lazy' => false])
        ->assertViewHas('slowRequests', collect([
            (object) ['method' => 'GET', 'uri' => '/users', 'action' => 'GlobalUserController@index', 'count' => 4, 'slowest' => 2468],
            (object) ['method' => 'GET', 'uri' => '{account}.example.com/users', 'action' => 'AccountUserController@index', 'count' => 2, 'slowest' => 1234],
        ]));
});

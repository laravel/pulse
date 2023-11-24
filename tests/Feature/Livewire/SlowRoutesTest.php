<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowRoutes;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowRoutes::class);
});

it('renders slow requests', function () {
    Route::get('/users', ['FooController', 'index']);
    Route::get('/users/{user}', fn () => 'users');
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_request', 'key' => 'GET /users', 'value' => 500],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_request', 'key' => 'GET /users', 'value' => 1234],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_request', 'key' => 'GET /users', 'value' => 2468],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_request', 'key' => 'GET /users/{user}', 'value' => 1234],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:count', 'key' => 'GET /users', 'value' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:count', 'key' => 'GET /users/{user}', 'value' => 1],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:max', 'key' => 'GET /users', 'value' => 1000],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:max', 'key' => 'GET /users/{user}', 'value' => 1000],
    ]));

    Livewire::test(SlowRoutes::class, ['lazy' => false])
        ->assertViewHas('slowRoutes', collect([
            (object) ['method' => 'GET', 'uri' => '/users', 'action' => 'FooController@index', 'count' => 5, 'slowest' => 2468],
            (object) ['method' => 'GET', 'uri' => '/users/{user}', 'action' => 'Closure', 'count' => 2, 'slowest' => 1234],
        ]));
});

it('handles routes with domains', function () {
    Route::domain('{account}.example.com')->group(function () {
        Route::get('users', ['AccountUserController', 'index']);
    });
    Route::get('users', ['GlobalUserController', 'index']);
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_request', 'key' => 'GET /users', 'value' => 2468],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_request', 'key' => 'GET {account}.example.com/users', 'value' => 1234],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:count', 'key' => 'GET /users', 'value' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:count', 'key' => 'GET {account}.example.com/users', 'value' => 1],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:max', 'key' => 'GET /users', 'value' => 1000],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_request:max', 'key' => 'GET {account}.example.com/users', 'value' => 1000],
    ]));

    Livewire::test(SlowRoutes::class, ['lazy' => false])
        ->assertViewHas('slowRoutes', collect([
            (object) ['method' => 'GET', 'uri' => '/users', 'action' => 'GlobalUserController@index', 'count' => 3, 'slowest' => 2468],
            (object) ['method' => 'GET', 'uri' => '{account}.example.com/users', 'action' => 'AccountUserController@index', 'count' => 2, 'slowest' => 1234],
        ]));
});

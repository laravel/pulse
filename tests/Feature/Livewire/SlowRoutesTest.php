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

it('renders slow routes', function () {
    Route::get('/users', ['FooController', 'index']);
    Route::get('/users/{user}', fn () => 'users');
    Pulse::ignore(fn () => DB::table('pulse_requests')->insert([
        ['date' => '2000-01-02 03:04:05', 'route' => 'GET /users', 'duration' => 1234],
        ['date' => '2000-01-02 03:04:05', 'route' => 'GET /users', 'duration' => 2468],
        ['date' => '2000-01-02 03:04:05', 'route' => 'GET /users/{user}', 'duration' => 1234],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(SlowRoutes::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('slowRoutes', collect([
            (object) ['route' => 'GET /users', 'action' => 'FooController@index', 'count' => 2, 'slowest' => 2468],
            (object) ['route' => 'GET /users/{user}', 'action' => 'Closure', 'count' => 1, 'slowest' => 1234],
        ]))
        ->assertViewHas('config');
});

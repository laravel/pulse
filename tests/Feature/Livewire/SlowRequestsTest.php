<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowRequests;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowRequests::class);
});

it('renders slow requests', function () {
    $request1 = json_encode(['GET', '/users', 'FooController@index', null]);
    $request2 = json_encode(['GET', '/users/{user}', 'Closure', null]);

    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_request', $request1, 1)->max()->count();
    Pulse::record('slow_request', $request2, 1)->max()->count();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_request', $request1, 1234)->max()->count();
    Pulse::record('slow_request', $request1, 2468)->max()->count();
    Pulse::record('slow_request', $request2, 1234)->max()->count();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('slow_request', $request1, 1000)->max()->count();
    Pulse::record('slow_request', $request1, 1000)->max()->count();
    Pulse::record('slow_request', $request2, 1000)->max()->count();

    Pulse::ingest();

    Livewire::test(SlowRequests::class, ['lazy' => false])
        ->assertViewHas('slowRequests', collect([
            (object) ['method' => 'GET', 'uri' => '/users', 'action' => 'FooController@index', 'count' => 4, 'slowest' => 2468],
            (object) ['method' => 'GET', 'uri' => '/users/{user}', 'action' => 'Closure', 'count' => 2, 'slowest' => 1234],
        ]));
});

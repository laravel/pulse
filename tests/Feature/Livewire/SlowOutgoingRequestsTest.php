<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Ingests\Storage;
use Laravel\Pulse\Livewire\SlowOutgoingRequests;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowOutgoingRequests::class);
});

it('renders slow outgoing requests', function () {
    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_outgoing_request', 'GET http://example.com', 1)->max();
    Pulse::record('slow_outgoing_request', 'GET http://example.org', 1)->max();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_outgoing_request', 'GET http://example.com', 1234)->max();
    Pulse::record('slow_outgoing_request', 'GET http://example.com', 2468)->max();
    Pulse::record('slow_outgoing_request', 'GET http://example.org', 1234)->max();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('slow_outgoing_request', 'GET http://example.com', 1000)->max();
    Pulse::record('slow_outgoing_request', 'GET http://example.com', 1000)->max();
    Pulse::record('slow_outgoing_request', 'GET http://example.org', 1000)->max();

    Pulse::store(app(Storage::class));

    Livewire::test(SlowOutgoingRequests::class, ['lazy' => false])
        ->assertViewHas('slowOutgoingRequests', collect([
            (object) ['method' => 'GET', 'uri' => 'http://example.com', 'count' => 4, 'slowest' => 2468],
            (object) ['method' => 'GET', 'uri' => 'http://example.org', 'count' => 2, 'slowest' => 1234],
        ]))
        ->assertViewHas('supported', true);
});

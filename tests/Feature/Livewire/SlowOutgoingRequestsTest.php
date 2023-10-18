<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowOutgoingRequests;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowOutgoingRequests::class);
});

it('renders slow outgoing requests', function () {
    Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->insert([
        ['date' => '2000-01-02 03:04:05', 'uri' => 'GET http://example.com', 'duration' => 1234],
        ['date' => '2000-01-02 03:04:05', 'uri' => 'GET http://example.com', 'duration' => 2468],
        ['date' => '2000-01-02 03:04:05', 'uri' => 'GET http://example.org', 'duration' => 123],
        ['date' => '2000-01-02 03:04:05', 'uri' => 'GET http://example.org', 'duration' => 1234],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(SlowOutgoingRequests::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('slowOutgoingRequests', collect([
            (object) ['uri' => 'GET http://example.com', 'count' => 2, 'slowest' => 2468],
            (object) ['uri' => 'GET http://example.org', 'count' => 1, 'slowest' => 1234],
        ]))
        ->assertViewHas('supported', true)
        ->assertViewHas('config');
});

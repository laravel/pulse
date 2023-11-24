<?php

use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowOutgoingRequests;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowOutgoingRequests::class);
});

it('renders slow outgoing requests', function () {
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_outgoing_request', 'key' => 'GET http://example.com', 'value' => 1234],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_outgoing_request', 'key' => 'GET http://example.com', 'value' => 2468],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_outgoing_request', 'key' => 'GET http://example.org', 'value' => 1234],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_outgoing_request:count', 'key' => 'GET http://example.com', 'value' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_outgoing_request:count', 'key' => 'GET http://example.org', 'value' => 1],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_outgoing_request:max', 'key' => 'GET http://example.com', 'value' => 1000],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_outgoing_request:max', 'key' => 'GET http://example.org', 'value' => 1000],
    ]));

    Livewire::test(SlowOutgoingRequests::class, ['lazy' => false])
        ->assertViewHas('slowOutgoingRequests', collect([
            (object) ['method' => 'GET', 'uri' => 'http://example.com', 'count' => 4, 'slowest' => 2468],
            (object) ['method' => 'GET', 'uri' => 'http://example.org', 'count' => 2, 'slowest' => 1234],
        ]))
        ->assertViewHas('supported', true);
});

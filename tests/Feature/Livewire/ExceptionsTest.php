<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Exceptions;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Exceptions::class);
});

it('renders exceptions', function () {
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => json_encode(['RuntimeException', 'app/Foo.php:123']), 'value' => $timestamp - 3600 + 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => json_encode(['RuntimeException', 'app/Bar.php:123']), 'value' => $timestamp - 3600 + 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => json_encode(['RuntimeException', 'app/Foo.php:123']), 'value' => $timestamp - 3600 + 1],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception', 'aggregate' => 'max', 'key' => json_encode(['RuntimeException', 'app/Foo.php:123']), 'value' => $timestamp, 'count' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception', 'aggregate' => 'max', 'key' => json_encode(['RuntimeException', 'app/Bar.php:123']), 'value' => $timestamp, 'count' => 1],
    ]));

    Livewire::test(Exceptions::class, ['lazy' => false])
        ->assertViewHas('exceptions', collect([
            (object) ['class' => 'RuntimeException', 'location' => 'app/Foo.php:123', 'count' => 4, 'latest' => $timestamp],
            (object) ['class' => 'RuntimeException', 'location' => 'app/Bar.php:123', 'count' => 2, 'latest' => $timestamp],
        ]));
});

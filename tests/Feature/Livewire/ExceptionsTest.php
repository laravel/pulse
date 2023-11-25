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
    Pulse::ignore(fn () => DB::table('pulse_values')->insert([
        ['type' => 'exception:latest', 'key' => 'RuntimeException::app/Foo.php:123', 'value' => $timestamp - 3600 + 1, 'timestamp' => $timestamp - 3600 + 1],
        ['type' => 'exception:latest', 'key' => 'RuntimeException::app/Bar.php:123', 'value' => $timestamp - 3600 + 1, 'timestamp' => $timestamp - 3600 + 1],
    ]));
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => 'RuntimeException::app/Foo.php:123', 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => 'RuntimeException::app/Bar.php:123', 'value' => 1],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => 'RuntimeException::app/Foo.php:123', 'value' => 1],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception:sum', 'key' => 'RuntimeException::app/Foo.php:123', 'value' => 2, 'count' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception:sum', 'key' => 'RuntimeException::app/Bar.php:123', 'value' => 1, 'count' => 1],
    ]));

    Livewire::test(Exceptions::class, ['lazy' => false])
        ->assertViewHas('exceptions', collect([
            (object) ['class' => 'RuntimeException', 'location' => 'app/Foo.php:123', 'count' => 4, 'latest' => $timestamp - 3600 + 1],
            (object) ['class' => 'RuntimeException', 'location' => 'app/Bar.php:123', 'count' => 2, 'latest' => $timestamp - 3600 + 1],
        ]));
});

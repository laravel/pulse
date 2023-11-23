<?php

use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Exceptions;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(Exceptions::class);
});

it('renders exceptions', function () {
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => 'RuntimeException::app/Foo.php:123'],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => 'RuntimeException::app/Bar.php:123'],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'exception', 'key' => 'RuntimeException::app/Foo.php:123'],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception:count', 'key' => 'RuntimeException::app/Foo.php:123', 'value' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception:count', 'key' => 'RuntimeException::app/Bar.php:123', 'value' => 1],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception:max', 'key' => 'RuntimeException::app/Foo.php:123', 'value' => $timestamp],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'exception:max', 'key' => 'RuntimeException::app/Bar.php:123', 'value' => $timestamp],
    ]));

    Livewire::test(Exceptions::class, ['lazy' => false])
        ->assertViewHas('exceptions', collect([
            (object) ['class' => 'RuntimeException', 'location' => 'app/Foo.php:123', 'count' => 4, 'latest' => $timestamp],
            (object) ['class' => 'RuntimeException', 'location' => 'app/Bar.php:123', 'count' => 2, 'latest' => $timestamp],
        ]));
});

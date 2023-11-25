<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowQueries;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowQueries::class);
});

it('renders slow queries', function () {
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_query', 'key' => 'select * from `users`::app/Foo.php:123', 'value' => 1234],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_query', 'key' => 'select * from `users`::app/Foo.php:123', 'value' => 2468],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_query', 'key' => 'select * from `users` where `id` = ?::app/Bar.php:456', 'value' => 1234],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_query:count', 'key' => 'select * from `users`::app/Foo.php:123', 'value' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_query:count', 'key' => 'select * from `users` where `id` = ?::app/Bar.php:456', 'value' => 1],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_query:max', 'key' => 'select * from `users`::app/Foo.php:123', 'value' => 1000],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_query:max', 'key' => 'select * from `users` where `id` = ?::app/Bar.php:456', 'value' => 1000],
    ]));

    Livewire::test(SlowQueries::class, ['lazy' => false])
        ->assertViewHas('slowQueries', collect([
            (object) ['sql' => 'select * from `users`', 'location' => 'app/Foo.php:123', 'count' => 4, 'slowest' => 2468],
            (object) ['sql' => 'select * from `users` where `id` = ?', 'location' => 'app/Bar.php:456', 'count' => 2, 'slowest' => 1234],
        ]));
});

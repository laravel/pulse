<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Exceptions;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Exceptions::class);
});

it('renders exceptions', function () {
    $exception1 = json_encode(['RuntimeException', 'app/Foo.php:123']);
    $exception2 = json_encode(['RuntimeException', 'app/Bar.php:123']);

    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('exception', $exception1, now()->timestamp)->max();
    Pulse::record('exception', $exception2, now()->timestamp)->max();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('exception', $exception1, now()->timestamp)->max();
    Pulse::record('exception', $exception1, now()->timestamp)->max();
    Pulse::record('exception', $exception2, now()->timestamp)->max();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('exception', $exception1, now()->timestamp)->max();
    Pulse::record('exception', $exception1, now()->timestamp)->max();
    Pulse::record('exception', $exception2, now()->timestamp)->max();

    Pulse::store();

    Livewire::test(Exceptions::class, ['lazy' => false])
        ->assertViewHas('exceptions', collect([
            (object) ['class' => 'RuntimeException', 'location' => 'app/Foo.php:123', 'count' => 4, 'latest' => now()->timestamp],
            (object) ['class' => 'RuntimeException', 'location' => 'app/Bar.php:123', 'count' => 2, 'latest' => now()->timestamp],
        ]));
});

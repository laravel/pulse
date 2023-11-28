<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowQueries;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowQueries::class);
});

it('renders slow queries', function () {
    $query1 = json_encode(['select * from `users`', 'app/Foo.php:123']);
    $query2 = json_encode(['select * from `users` where `id` = ?', 'app/Bar.php:456']);

    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_query', $query1, 1)->max();
    Pulse::record('slow_query', $query2, 1)->max();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_query', $query1, 1234)->max();
    Pulse::record('slow_query', $query1, 2468)->max();
    Pulse::record('slow_query', $query2, 1234)->max();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('slow_query', $query1, 1000)->max();
    Pulse::record('slow_query', $query1, 1000)->max();
    Pulse::record('slow_query', $query2, 1000)->max();

    Pulse::store();

    Livewire::test(SlowQueries::class, ['lazy' => false])
        ->assertViewHas('slowQueries', collect([
            (object) ['sql' => 'select * from `users`', 'location' => 'app/Foo.php:123', 'count' => 4, 'slowest' => 2468],
            (object) ['sql' => 'select * from `users` where `id` = ?', 'location' => 'app/Bar.php:456', 'count' => 2, 'slowest' => 1234],
        ]));
});

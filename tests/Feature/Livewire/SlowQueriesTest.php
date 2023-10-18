<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowQueries;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowQueries::class);
});

it('renders slow queries', function () {
    Pulse::ignore(fn () => DB::table('pulse_slow_queries')->insert([
        ['date' => '2000-01-02 03:04:05', 'sql' => 'select * from `users`', 'duration' => 1234],
        ['date' => '2000-01-02 03:04:05', 'sql' => 'select * from `users`', 'duration' => 2468],
        ['date' => '2000-01-02 03:04:05', 'sql' => 'select * from `users` where `id` = ?', 'duration' => 1234],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(SlowQueries::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('slowQueries', collect([
            (object) ['sql' => 'select * from `users`', 'count' => 2, 'slowest' => 2468],
            (object) ['sql' => 'select * from `users` where `id` = ?', 'count' => 1, 'slowest' => 1234],
        ]));
});

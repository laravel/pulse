<?php

use Illuminate\Support\Carbon;
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
    Pulse::ignore(fn () => DB::table('pulse_exceptions')->insert([
        ['date' => '2000-01-02 03:04:05', 'class' => 'RuntimeException', 'location' => 'app/Foo.php'],
        ['date' => '2000-01-02 03:04:05', 'class' => 'RuntimeException', 'location' => 'app/Bar.php'],
        ['date' => '2000-01-02 03:04:10', 'class' => 'RuntimeException', 'location' => 'app/Foo.php'],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(Exceptions::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('exceptions', collect([
            (object) ['class' => 'RuntimeException', 'location' => 'app/Foo.php', 'count' => 2, 'last_occurrence' => '2000-01-02 03:04:10'],
            (object) ['class' => 'RuntimeException', 'location' => 'app/Bar.php', 'count' => 1, 'last_occurrence' => '2000-01-02 03:04:05'],
        ]));
});

<?php

use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Servers;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(Servers::class);
});

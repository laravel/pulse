<?php

use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Usage;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(Usage::class);
});

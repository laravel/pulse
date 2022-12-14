<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Servers extends Component
{
    public function getServersProperty()
    {
        return app(Pulse::class)->servers();
    }

    public function render()
    {
        return view('pulse::livewire.servers');
    }
}

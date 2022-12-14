<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowEndpoints extends Component
{
    public function getSlowEndpointsProperty()
    {
        return app(Pulse::class)->slowEndpoints();
    }

    public function render()
    {
        return view('pulse::livewire.slow-endpoints');
    }
}

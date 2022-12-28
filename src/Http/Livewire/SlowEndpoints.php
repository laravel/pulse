<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowEndpoints extends Component
{
    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.slow-endpoints', [
            'slowEndpoints' => $pulse->slowEndpoints(),
        ]);
    }
}

<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Queues extends Component
{
    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.queues', [
            'queues' => $pulse->queues(),
        ]);
    }
}

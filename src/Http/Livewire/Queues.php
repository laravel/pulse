<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Queues extends Component
{
    public function getQueuesProperty()
    {
        return app(Pulse::class)->queues();
    }

    public function render()
    {
        return view('pulse::livewire.queues');
    }
}

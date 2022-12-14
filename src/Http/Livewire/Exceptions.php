<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Exceptions extends Component
{
    public function getExceptionsProperty()
    {
        return app(Pulse::class)->exceptions();
    }

    public function render()
    {
        return view('pulse::livewire.exceptions');
    }
}

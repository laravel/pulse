<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Cache extends Component
{
    public function getCacheProperty()
    {
        return app(Pulse::class)->cache();
    }

    public function render()
    {
        return view('pulse::livewire.cache');
    }
}

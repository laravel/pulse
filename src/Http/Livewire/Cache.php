<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Cache extends Component
{
    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.cache', [
            'cacheStats' => $pulse->cacheStats(),
        ]);
    }
}

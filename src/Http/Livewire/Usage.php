<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Usage extends Component
{
    public function getUsageProperty()
    {
        return app(Pulse::class)->usage();
    }

    public function render()
    {
        return view('pulse::livewire.usage');
    }
}

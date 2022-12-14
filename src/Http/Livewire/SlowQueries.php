<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowQueries extends Component
{
    public function getSlowQueriesProperty()
    {
        return app(Pulse::class)->slowQueries();
    }

    public function render()
    {
        return view('pulse::livewire.slow-queries');
    }
}

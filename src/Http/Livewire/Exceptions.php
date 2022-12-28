<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Pulse;
use Livewire\Component;

class Exceptions extends Component
{
    public string $sortBy = 'count';

    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.exceptions', [
            'exceptions' => $pulse->exceptions()->sortByDesc($this->sortBy),
        ]);
    }
}

<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class Exceptions extends Component implements ShouldNotReportUsage
{
    public string $sortBy = 'count';

    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.exceptions', [
            'exceptions' => $pulse->exceptions()->sortByDesc($this->sortBy),
        ]);
    }
}

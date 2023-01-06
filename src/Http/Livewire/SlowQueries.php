<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class SlowQueries extends Component implements ShouldNotReportUsage
{
    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.slow-queries', [
            'slowQueries' => $pulse->slowQueries(),
        ]);
    }
}

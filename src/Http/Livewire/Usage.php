<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class Usage extends Component implements ShouldNotReportUsage
{
    public string $view = 'request-counts';

    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.usage', [
            'userRequestCounts' => $pulse->userRequestCounts(),
            'usersExperiencingSlowEndpoints' => $pulse->usersExperiencingSlowEndpoints(),
        ]);
    }
}

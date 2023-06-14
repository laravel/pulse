<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class PeriodSelector extends Component implements ShouldNotReportUsage
{
    public $period = '1-hour';

    protected $queryString = [
        'period' => ['except' => '1-hour'],
    ];

    public function render(Pulse $pulse)
    {
        return view('pulse::livewire.period-selector');
    }
}

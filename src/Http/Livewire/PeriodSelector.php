<?php

namespace Laravel\Pulse\Http\Livewire;

use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class PeriodSelector extends Component implements ShouldNotReportUsage
{
    /**
     * The selected period.
     *
     * @var '1_hour'|'6_hours'|'24_hours'|'7_days'|null
     */
    public $period = '1_hour';

    /**
     * The query string parameters.
     *
     * @var array
     */
    protected $queryString = [
        'period' => ['except' => '1_hour'],
    ];

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('pulse::livewire.period-selector');
    }

    public function setPeriod($period)
    {
        $this->period = $period;
        $this->emit('periodChanged', $period);
    }
}

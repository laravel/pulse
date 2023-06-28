<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class PeriodSelector extends Component implements ShouldNotReportUsage
{
    /**
     * The selected period.
     *
     * @var '1_hour'|'6_hours'|'24_hours'|'7_days'
     */
    public string $period = '1_hour';

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
     */
    public function render(): Renderable
    {
        return view('pulse::livewire.period-selector');
    }

    /**
     * Set the selected period.
     */
    public function setPeriod(string $period): void
    {
        $this->period = $period;

        $this->emit('periodChanged', $period);
    }
}

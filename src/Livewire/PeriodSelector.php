<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Attributes\Url;
use Livewire\Component;

class PeriodSelector extends Component
{
    use ShouldNotReportUsage;

    /**
     * The selected period.
     *
     * @var '1_hour'|'6_hours'|'24_hours'|'7_days'
     */
    #[Url]
    public string $period = '1_hour';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        return view('pulse::livewire.period-selector');
    }

    /**
     * Set the selected period.
     *
     * @param  '1_hour'|'6_hours'|'24_hours'|'7_days'  $period
     */
    public function setPeriod(string $period): void
    {
        $this->period = $period;

        $this->dispatch('period-changed', period: $period);
    }
}

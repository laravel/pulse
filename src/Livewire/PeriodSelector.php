<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @internal
 */
class PeriodSelector extends Component
{
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
        return View::make('pulse::livewire.period-selector');
    }
}

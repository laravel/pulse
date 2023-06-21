<?php

namespace Laravel\Pulse\Http\Livewire\Concerns;

trait HasPeriod
{
    /**
     * The usage period.
     *
     * @var '1_hour'|6_hours'|'24_hours'|'7_days'|null
     */
    public $period;

    /**
     * Initialize the trait.
     *
     * @return void
     */
    public function initializeHasPeriod()
    {
        $this->listeners[] = 'periodChanged';

        $this->period = (request()->query('period') ?: $this->period) ?: '1_hour';
    }

    /**
     * Handle the periodChanged event.
     *
     * @param  '1_hour'|6_hours'|'24_hours'|'7_days'  $period
     * @return void
     */
    public function periodChanged($period)
    {
        $this->period = $period;
    }

}

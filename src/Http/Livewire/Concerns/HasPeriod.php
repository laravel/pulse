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

    /**
     * Get the number of seconds in the period.
     *
     * @return int
     */
    public function periodSeconds()
    {
        return match ($this->period) {
            '7_days' => 604800,
            '24_hours' => 86400,
            '6_hours' => 21600,
            default => 3600,
        };
    }
}

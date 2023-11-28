<?php

namespace Laravel\Pulse\Livewire\Concerns;

use Carbon\CarbonInterval as Interval;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

trait HasPeriod
{
    /**
     * The usage period.
     *
     * @var '1_hour'|'6_hours'|'24_hours'|'7_days'|null
     */
    #[Url]
    public ?string $period = '1_hour';

    /**
     * Handle the period-changed event.
     *
     * @param  '1_hour'|'6_hours'|'24_hours'|'7_days'  $period
     */
    #[On('period-changed')]
    public function updatePeriod(string $period): void
    {
        $this->period = $period;
    }

    /**
     * The period as an Interval instance.
     */
    public function periodAsInterval(): Interval
    {
        return Interval::hours(match ($this->period) {
            '6_hours' => 6,
            '24_hours' => 24,
            '7_days' => 168,
            default => 1,
        });
    }

    /**
     * The human friendly representation of the selected period.
     */
    public function periodForHumans(): string
    {
        return match ($this->period) {
            '6_hours' => '6 hours',
            '24_hours' => '24 hours',
            '7_days' => '7 days',
            default => 'hour',
        };
    }
}

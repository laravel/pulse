<?php

namespace Laravel\Pulse\Livewire\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * @method string recorder()
 */
trait HasThreshold
{
    /**
     * Get the threshold for the given value.
     */
    public function threshold(string $value): int
    {
        $custom = collect(Config::get('pulse.recorders.'.$this->recorder().'.threshold'))
            ->except(['default'])
            ->first(fn (int|float $threshold, string $pattern) => preg_match($pattern, $value));

        return $custom ?? Config::get('pulse.recorders.'.$this->recorder().'.threshold.default');
    }
}

<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Illuminate\Support\Lottery;

trait Sampling
{
    /**
     * Determine if the event should be sampled.
     */
    protected function shouldSample(): bool
    {
        $rate = $this->config->get('pulse.recorders.'.static::class.'.sample_rate');

        return Lottery::odds($rate)->choose();
    }

    /**
     * Determine if the event should be sampled deterministically.
     */
    protected function shouldSampleDeterministically(string $seed): bool
    {
        $rate = $this->config->get('pulse.recorders.'.static::class.'.sample_rate');

        $value = hexdec(md5($seed)) / pow(16, 32); // Scale to 0-1

        return $value <= $rate;
    }
}

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
        return Lottery::odds(
            $this->config->get('pulse.recorders.'.self::class.'.sample_rate')
        )->choose();
    }

    /**
     * Determine if the event should be sampled deterministically.
     */
    protected function shouldSampleDeterministically(string $seed): bool
    {
        $value = hexdec(md5($seed)) / pow(16, 32); // Scale to 0-1

        return $value <= $this->config->get('pulse.recorders.'.self::class.'.sample_rate');
    }
}

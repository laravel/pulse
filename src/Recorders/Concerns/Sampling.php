<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Lottery;

trait Sampling
{
    /**
     * Determine if the event should be sampled.
     */
    protected function shouldSample(): bool
    {
        return Lottery::odds(
            Config::get('pulse.recorders.'.static::class.'.sample_rate', 1)
        )->choose();
    }

    /**
     * Determine if the event should be sampled deterministically.
     */
    protected function shouldSampleDeterministically(string $seed): bool
    {
        $value = hexdec(md5($seed)) / pow(16, 32); // Scale to 0-1

        return $value <= Config::get('pulse.recorders.'.static::class.'.sample_rate', 1);
    }
}

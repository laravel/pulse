<?php

namespace Laravel\Pulse\Recorders\Concerns;

trait Thresholds
{
    /**
     * Determine if the duration is under the configured threshold.
     */
    protected function underThreshold(int|float $duration): bool
    {
        return $duration < $this->config->get('pulse.recorders.'.self::class.'.threshold');
    }
}

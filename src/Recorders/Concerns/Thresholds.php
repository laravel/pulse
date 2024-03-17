<?php

namespace Laravel\Pulse\Recorders\Concerns;

trait Thresholds
{
    /**
     * Determine if the duration is under the configured threshold.
     */
    protected function underThreshold(int|float $duration, string $value): bool
    {
        return $duration < $this->threshold($value);
    }


    /**
     * Get the threshold for the given value.
     */
    protected function threshold(string $value): int
    {
        // @phpstan-ignore argument.templateType, argument.templateType
        $custom = collect($this->config->get('pulse.recorders.'.static::class.'.threshold'))
            ->except(['default'])
            ->first(fn ($threshold, string $pattern) => !! preg_match($pattern, $value));

        return $custom ?? $this->config->get('pulse.recorders.'.static::class.'.threshold.default');
    }
}

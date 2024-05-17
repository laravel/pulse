<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Illuminate\Support\Facades\Config;

trait Thresholds
{
    /**
     * Determine if the duration is under the configured threshold.
     */
    protected function underThreshold(int|float $duration, string $key): bool
    {
        return $duration < $this->threshold($key);
    }

    /**
     * Get the threshold for the given key.
     */
    protected function threshold(string $key, ?string $recorder = null): int
    {
        $recorder ??= static::class;

        $config = Config::get("pulse.recorders.{$recorder}.threshold", 1_000);

        if (! is_array($config)) {
            return $config;
        }

        // @phpstan-ignore argument.templateType, argument.templateType
        $custom = collect($config)
            ->except(['default'])
            ->first(fn ($threshold, $pattern) => preg_match($pattern, $key) === 1);

        return $custom ?? $config['default'] ?? 1_000;
    }
}

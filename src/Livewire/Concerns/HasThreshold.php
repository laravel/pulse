<?php

namespace Laravel\Pulse\Livewire\Concerns;

use Illuminate\Support\Facades\Config;

trait HasThreshold
{
    /**
     * Get the threshold for the given value.
     * @param  class-string  $recorder
     */
    public function threshold(string $value, string $recorder): int
    {
        $thresholdConfig = Config::get('pulse.recorders.'.$recorder.'.threshold');
        if (!is_array($thresholdConfig)) {
            return $thresholdConfig;
        }

        // @phpstan-ignore argument.templateType, argument.templateType
        $custom = collect($thresholdConfig)
            ->except(['default'])
            ->first(fn ($threshold, string $pattern) => !! preg_match($pattern, $value));

        return $custom ?? $thresholdConfig['default'];
    }
}

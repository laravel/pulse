<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Illuminate\Support\Facades\Config;

trait Ignores
{
    /**
     * Determine if the given value should be ignored.
     */
    protected function shouldIgnore(string $value): bool
    {
        // @phpstan-ignore argument.templateType, argument.templateType
        return collect(Config::get('pulse.recorders.'.static::class.'.ignore', []))
            ->contains(fn (string $pattern) => preg_match($pattern, $value));
    }
}

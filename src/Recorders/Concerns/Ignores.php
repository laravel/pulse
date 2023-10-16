<?php

namespace Laravel\Pulse\Recorders\Concerns;

trait Ignores
{
    /**
     * Determine if the given value should be ignored.
     */
    public function shouldIgnore(string $value): bool
    {
        return collect($this->config->get('pulse.recorders.'.static::class.'.ignore'))
            ->contains(fn (string $pattern) => preg_match($pattern, $value));
    }
}

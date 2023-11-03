<?php

namespace Laravel\Pulse\Recorders\Concerns;

trait Ignores
{
    /**
     * Determine if the given value should be ignored.
     */
    protected function shouldIgnore(string $value): bool
    {
        // @phpstan-ignore argument.templateType, argument.templateType
        return collect($this->config->get('pulse.recorders.'.self::class.'.ignore'))
            ->contains(fn (string $pattern) => preg_match($pattern, $value));
    }
}

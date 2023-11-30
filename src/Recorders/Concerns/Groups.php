<?php

namespace Laravel\Pulse\Recorders\Concerns;

/**
 * @internal
 */
trait Groups
{
    /**
     * The grouped value.
     */
    protected function group(string $value): string
    {
        foreach ($this->config->get('pulse.recorders.'.self::class.'.groups') as $pattern => $replacement) {
            $group = preg_replace($pattern, $replacement, $value, count: $count);

            if ($count > 0 && $group !== null) {
                return $group;
            }
        }

        return $value;
    }
}

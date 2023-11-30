<?php

namespace Laravel\Pulse\Recorders\Concerns;

trait Groups
{
    /**
     * Group the value based on the configured grouping rules.
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

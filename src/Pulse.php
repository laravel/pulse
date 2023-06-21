<?php

namespace Laravel\Pulse;

class Pulse
{
    /**
     * Indicates if Pulse migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    public bool $doNotReportUsage = false;

    public function cacheStats()
    {
        $hits = collect(range(0, 6))
            ->map(fn ($days) => RedisAdapter::get('pulse_cache_hits:'.now()->subDays($days)->format('Y-m-d')))
            ->sum();

        $misses = collect(range(0, 6))
            ->map(fn ($days) => RedisAdapter::get('pulse_cache_misses:'.now()->subDays($days)->format('Y-m-d')))
            ->sum();

        $total = $hits + $misses;

        if ($total === 0) {
            $rate = 0;
        } else {
            $rate = (int) (($hits / $total) * 100);
        }

        return [
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $rate,
        ];
    }

    public function css()
    {
        return file_get_contents(__DIR__.'/../dist/pulse.css');
    }

    public function js()
    {
        return file_get_contents(__DIR__.'/../dist/pulse.js');
    }

    /**
     * Configure Pulse to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }
}

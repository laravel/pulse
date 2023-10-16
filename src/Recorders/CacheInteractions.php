<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class CacheInteractions
{
    /**
     * The table to record to.
     */
    public string $table = 'pulse_cache_interactions';

    /**
     * The events to listen for.
     *
     * @var list<class-string>
     */
    public array $listen = [
        CacheHit::class,
        CacheMissed::class,
    ];

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Record the cache interaction.
     */
    public function record(CacheHit|CacheMissed $event): ?Entry
    {
        $now = new CarbonImmutable();

        if ($this->shouldIgnoreKey($event->key)) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $now->toDateTimeString(),
            'hit' => $event instanceof CacheHit,
            'key' => $this->normalizeKey($event->key),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Determine if the key should be ignored.
     */
    protected function shouldIgnoreKey(string $key): bool
    {
        $ignore = $this->config->get('pulse.recorders.'.static::class.'.ignore');

        foreach ($ignore as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize the cache key.
     */
    protected function normalizeKey(string $key): Closure
    {
        return function () use ($key) {
            foreach ($this->config->get('pulse.recorders.'.static::class.'.groups') as $pattern => $replacement) {
                $normalized = preg_replace($pattern, $replacement, $key, count: $count);

                if ($count > 0 && $normalized !== null) {
                    return $normalized;
                }
            }

            return $key;
        };
    }
}

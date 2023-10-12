<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository;
use Illuminate\Support\Str;
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

        if (
            Str::startsWith($event->key, ['illuminate:', 'laravel:pulse:']) ||
            Str::endsWith($event->key, ':timer')
        ) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $now->toDateTimeString(),
            'hit' => $event instanceof CacheHit,
            'key' => $this->normalizeKey($event),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Normalize the cache key.
     */
    protected function normalizeKey(CacheHit|CacheMissed $event): Closure
    {
        $key = $event->key;

        return function () use ($key) {
            foreach ($this->config->get('pulse.cache_keys') as $pattern => $replacement) {
                $normalized = preg_replace($pattern, $replacement, $key, count: $count);

                if ($count > 0 && $normalized !== null) {
                    return $normalized;
                }
            }

            return $key;
        };
    }
}

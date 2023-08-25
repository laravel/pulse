<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
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
    public array $listen = [CacheHit::class, CacheMissed::class];

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Record the cache interaction.
     */
    public function record(CacheHit|CacheMissed $event): ?Entry
    {
        $now = new CarbonImmutable();

        if (Str::startsWith($event->key, ['illuminate:', 'laravel:pulse'])) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $now->toDateTimeString(),
            'hit' => $event instanceof CacheHit,
            'key' => $event->key,
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }
}

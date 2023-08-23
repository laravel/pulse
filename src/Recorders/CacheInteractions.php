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
    /** @var list<string> */
    public array $tables = ['pulse_cache_hits'];

    /** @var list<class-string> */
    public array $events = [CacheHit::class, CacheMissed::class];

    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Handle a cache miss.
     */
    public function record(CacheHit|CacheMissed $event): ?Entry
    {
        $now = new CarbonImmutable();

        if (Str::startsWith($event->key, ['illuminate:', 'laravel:pulse'])) {
            return null;
        }

        return new Entry('pulse_cache_hits', [
            'date' => $now->toDateTimeString(),
            'hit' => $event instanceof CacheHit,
            'key' => $event->key,
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }
}

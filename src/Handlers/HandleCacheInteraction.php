<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;

class HandleCacheInteraction
{
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
    public function __invoke(CacheHit|CacheMissed $event): void
    {
        $this->pulse->rescue(function () use ($event) {
            $now = new CarbonImmutable();

            if (Str::startsWith($event->key, ['illuminate:', 'laravel:pulse'])) {
                return;
            }

            $this->pulse->record(new Entry('pulse_cache_hits', [
                'date' => $now->toDateTimeString(),
                'hit' => $event instanceof CacheHit,
                'key' => $event->key,
                'user_id' => $this->pulse->authenticatedUserIdResolver(),
            ]));
        });
    }
}

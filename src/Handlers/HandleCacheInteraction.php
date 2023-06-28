<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Laravel\Pulse\Pulse;

class HandleCacheInteraction
{
    public function __construct(protected Pulse $pulse)
    {
        //
    }

    /**
     * Handle a cache miss.
     */
    public function __invoke(CacheHit|CacheMissed $event): void
    {
        rescue(function () use ($event) {
            $now = now();

            if (str_starts_with($event->key, 'illuminate:')) {
                return;
            }

            $this->pulse->record('pulse_cache_hits', [
                'date' => $now->toDateTimeString(),
                'hit' => $event instanceof CacheHit,
                'key' => $event->key,
            ]);
        }, report: false);
    }
}

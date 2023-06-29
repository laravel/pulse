<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Auth;
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
            $now = new CarbonImmutable();

            if (str_starts_with($event->key, 'illuminate:')) {
                return;
            }

            $this->pulse->record('pulse_cache_hits', [
                'date' => $now->toDateTimeString(),
                'hit' => $event instanceof CacheHit,
                'key' => $event->key,
                'user_id' => Auth::id(),
            ]);
        }, report: false);
    }
}

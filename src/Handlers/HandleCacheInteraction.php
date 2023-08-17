<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Auth;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;
use Laravel\Pulse\Facades\Pulse;

class HandleCacheInteraction
{
    /**
     * Handle a cache miss.
     */
    public function __invoke(CacheHit|CacheMissed $event): void
    {
        Pulse::rescue(function () use ($event) {
            $now = new CarbonImmutable();

            if (str_starts_with($event->key, 'illuminate:')) {
                return;
            }

            Pulse::record(new Entry(Table::CacheHit, [
                'date' => $now->toDateTimeString(),
                'hit' => $event instanceof CacheHit,
                'key' => $event->key,
                'user_id' => Auth::hasUser() ? Auth::id() : fn () => Auth::id(),
            ]));
        });
    }
}

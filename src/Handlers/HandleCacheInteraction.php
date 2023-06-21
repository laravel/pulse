<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\DB;
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
        if ($this->pulse->doNotReportUsage) {
            return;
        }

        if ($event->key === 'illuminate:queue:restart') {
            return;
        }

        // TODO: tags?

        DB::table('pulse_cache_hits')->insert([
            'date' => now()->toDateTimeString(),
            'hit' => $event instanceof CacheHit,
            'key' => $event->key,
        ]);
    }
}

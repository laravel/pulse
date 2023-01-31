<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheHit;
use Laravel\Pulse\RedisAdapter;

class HandleCacheHit
{
    /**
     * Handle a cache hit.
     */
    public function __invoke(CacheHit $event): void
    {
        $keyDate = now()->format('Y-m-d');
        $keyExpiry = now()->toImmutable()->startOfDay()->addDays(7)->timestamp;
        $key = "pulse_cache_hits:{$keyDate}";

        RedisAdapter::incr($key);
        RedisAdapter::expireat($key, $keyExpiry, 'NX');
    }
}

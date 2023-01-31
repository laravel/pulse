<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheMissed;
use Laravel\Pulse\RedisAdapter;

class HandleCacheMiss
{
    /**
     * Handle a cache miss.
     */
    public function __invoke(CacheMissed $event): void
    {
        $keyDate = now()->format('Y-m-d');
        $keyExpiry = now()->toImmutable()->startOfDay()->addDays(7)->timestamp;
        $key = "pulse_cache_misses:{$keyDate}";

        RedisAdapter::incr($key);
        RedisAdapter::expireat($key, $keyExpiry, 'NX');
    }
}

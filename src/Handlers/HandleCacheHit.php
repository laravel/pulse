<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Support\Facades\Redis;

class HandleCacheHit
{
    /**
     * Handle a cache hit.
     */
    public function __invoke(CacheHit $event): void
    {
        $keyDate = now()->format('Y-m-d');
        $keyExpiry = now()->toImmutable()->startOfDay()->addDays(7)->timestamp;
        $keyPrefix = config('database.redis.options.prefix');
        $key = "pulse_cache_hits:{$keyDate}";

        Redis::incr($key);
        Redis::rawCommand('EXPIREAT', $keyPrefix.$key, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7
    }
}

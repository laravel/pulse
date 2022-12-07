<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Redis;

class HandleCacheMiss
{
    /**
     * Handle a cache miss.
     */
    public function __invoke(CacheMissed $event): void
    {
        $keyDate = now()->format('Y-m-d');
        $keyExpiry = now()->toImmutable()->startOfDay()->addDays(7)->timestamp;
        $keyPrefix = config('database.redis.options.prefix');
        $key = "pulse_cache_misses:{$keyDate}";

        Redis::incr($key);
        Redis::rawCommand('EXPIREAT', $keyPrefix.$key, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7
    }
}

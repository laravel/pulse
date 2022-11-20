<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheHit;

class HandleCacheHit
{
    /**
     * Handle a cache hit.
     */
    public function __invoke(CacheHit $event): void
    {
        ray('Cache Hit: '.$e->key);
    }
}

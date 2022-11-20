<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Cache\Events\CacheMissed;

class HandleCacheMiss
{
    /**
     * Handle a cache miss.
     */
    public function __invoke(CacheMissed $event): void
    {
        ray('Cache Miss: '.$e->key);
    }
}

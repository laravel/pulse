<?php

namespace Laravel\Pulse\Support;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;

class CacheStoreResolver
{
    /**
     * Create a new cache connection resolver instance.
     */
    public function __construct(
        protected CacheManager $cache,
        protected ConfigRepository $config,
    ) {
        //
    }

    /**
     * Get the cache connection.
     */
    public function store(): CacheRepository
    {
        return $this->cache->store($this->config->get('pulse.cache'));
    }
}

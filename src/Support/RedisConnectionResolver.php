<?php

namespace Laravel\Pulse\Support;

use Illuminate\Config\Repository;
use Illuminate\Redis\RedisManager;
use Laravel\Pulse\Redis;

class RedisConnectionResolver
{
    public function __construct(
        protected RedisManager $redis,
        protected Repository $config,
    ) {
        //
    }

    public function connection(): Redis
    {
        return new Redis($this->redis->connection(
            $this->config->get('pulse.ingest.redis.connection')
        ), $this->config);
    }
}

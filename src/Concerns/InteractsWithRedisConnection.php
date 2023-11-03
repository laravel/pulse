<?php

namespace Laravel\Pulse\Concerns;

use Laravel\Pulse\Redis;

/**
 * @internal
 */
trait InteractsWithRedisConnection
{
    /**
     * Get the query's Redis connection.
     */
    protected function redis(): Redis
    {
        return new Redis($this->redis->connection(
            $this->config->get('pulse.ingest.redis.connection')
        ), $this->config);
    }
}

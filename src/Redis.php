<?php

namespace Laravel\Pulse;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Predis\Client as Predis;
use Predis\Pipeline\Pipeline;
use Redis as PhpRedis;

/**
 * @internal
 */
class Redis
{
    /**
     * Create a new Redis instance.
     */
    public function __construct(
        protected Repository $config,
        protected Connection $connection,
        protected ?Pipeline $client = null,
    ) {
        //
    }

    /**
     * Add an entry to the stream.
     */
    public function xadd(string $key, array $dictionary)
    {
        return match (true) {
            $this->client() instanceof PhpRedis => $this->client()->xadd($key, '*', $dictionary),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->xadd($key, $dictionary),
        };
    }

    /**
     * Read a range of entries from the stream.
     */
    public function xrange(string $key, string $start, string $end, int $count = null): array
    {
        return $this->client()->xrange(...array_filter(func_get_args()));
    }

    /**
     * Trim the stream.
     */
    public function xtrim(string $key, string $strategy, string $strategyModifier, string|int $threshold)
    {
        $threshold = (string) $threshold;

        return match (true) {
            // PHP Redis does not support the minid strategy.
            $this->client() instanceof PhpRedis => $this->client()->rawCommand('XTRIM', $this->config->get('redis.options.prefix').$key, $strategy, $strategyModifier, $threshold),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->xtrim($key, [$strategy, $strategyModifier], $threshold),
        };
    }

    /**
     * Delete the entries from the stream.
     */
    public function xdel(string $stream, Collection|array $keys): int
    {
        return $this->client()->xdel($stream, Collection::unwrap($keys));
    }

    /**
     * Run commands within a pipeline.
     *
     * @param  (callable(self): void)  $closure
     */
    public function pipeline(callable $closure): array
    {
        // Create a pipeline and wrap the Redis client in an instance of this
        // class to ensure our wrapper methods are used within the pipeline.
        return $this->connection->pipeline(fn ($client) => $closure(new self($this->config, $this->connection, $client)));
    }

    /**
     * Retrieve the redis client.
     */
    protected function client(): PhpRedis|Predis|Pipeline
    {
        return $this->client ?? $this->connection->client();
    }
}

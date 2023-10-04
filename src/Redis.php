<?php

namespace Laravel\Pulse;

use Illuminate\Config\Repository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Predis\Client as Predis;
use Predis\Pipeline\Pipeline;
use Redis as PhpRedis;
use RuntimeException;

/**
 * @internal
 */
class Redis
{
    /**
     * Create a new Redis instance.
     */
    public function __construct(
        protected Connection $connection,
        protected Repository $config,
        protected Pipeline|PhpRedis|null $client = null,
    ) {
        //
    }

    /**
     * Add an entry to the stream.
     *
     * @param  array<string, string>  $dictionary
     */
    public function xadd(string $key, array $dictionary): string|Pipeline|PhpRedis
    {
        return match (true) {
            $this->client() instanceof PhpRedis => $this->client()->xadd($key, '*', $dictionary),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->xadd($key, $dictionary),
        };
    }

    /**
     * Read a range of entries from the stream.
     *
     * @return array<string, array<string, string>>
     */
    public function xrange(string $key, string $start, string $end, int $count = null): array
    {
        return $this->client()->xrange(...array_filter(func_get_args()));
    }

    /**
     * Trim the stream.
     */
    public function xtrim(string $key, string $strategy, string $strategyModifier, string|int $threshold): int
    {
        return match (true) {
            // PHP Redis does not support the minid strategy....
            $this->client() instanceof PhpRedis => $this->client()->rawCommand('XTRIM', $this->config->get('database.redis.options.prefix').$key, $strategy, $strategyModifier, (string) $threshold),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->xtrim($key, [$strategy, $strategyModifier], (string) $threshold),
        };
    }

    /**
     * Delete the entries from the stream.
     *
     * @param  \Illuminate\Support\Collection<int, string>|array<int, string>  $keys
     */
    public function xdel(string $stream, Collection|array $keys): int
    {
        return $this->client()->xdel($stream, Collection::unwrap($keys));
    }

    /**
     * Run commands within a pipeline.
     *
     * @param  (callable(self): void)  $closure
     * @return array<int, mixed>
     */
    public function pipeline(callable $closure): array
    {
        if ($this->client() instanceof Pipeline) {
            throw new RuntimeException('Pipelines are not able to be nested.');
        }

        // Create a pipeline and wrap the Redis client in an instance of this class to ensure our wrapper methods are used within the pipeline...
        return $this->connection->pipeline(fn (Pipeline|PhpRedis $client) => $closure(new self($this->connection, $this->config, $client)));
    }

    /**
     * Get the Redis client instance.
     */
    protected function client(): PhpRedis|Predis|Pipeline
    {
        return $this->client ?? $this->connection->client();
    }
}

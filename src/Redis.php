<?php

namespace Laravel\Pulse;

use Carbon\CarbonInterval;
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
     * @return list<string>
     */
    public function zrange(string $key, int $start, int $stop, bool $reversed = false, bool $withScores = false): array
    {
        return match (true) {
            $this->client() instanceof PhpRedis => $this->client()->rawCommand('ZRANGE', $this->config->get('database.redis.options.prefix').$key, $start, $stop, ...array_filter([
                $reversed ? 'REV' : null,
                $withScores ? 'WITHSCORES' : null,
            ])),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->executeRaw(['ZRANGE', $this->config->get('database.redis.options.prefix').$key, $start, $stop, ...array_filter([ // @phpstan-ignore method.notFound
                $reversed ? 'REV' : null,
                $withScores ? 'WITHSCORES' : null,
            ])]),
        };
    }

    /**
     * Get the key.
     */
    public function get(string $key): null|string|Pipeline|PhpRedis
    {
        return $this->client()->get($key); // @phpstan-ignore return.type
    }

    /**
     * Put the value.
     */
    public function set(string $key, string $value, CarbonInterval $ttl): null|string|Pipeline|PhpRedis
    {
        return match (true) {
            $this->client() instanceof PhpRedis => $this->client()->rawCommand('SET', $this->config->get('database.redis.options.prefix').$key, $value, 'PX', (int) $ttl->totalMilliseconds),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->set($key, $value, 'PX', (int) $ttl->totalMilliseconds),
        };
    }

    /**
     * Expire the key at the given time.
     */
    public function expire(string $key, CarbonInterval $interval): int|Pipeline|PhpRedis
    {
        return $this->client()->expire($key, (int) $interval->totalSeconds); // @phpstan-ignore return.type
    }

    /**
     * Delete the key.
     *
     * @param  list<string>  $keys
     */
    public function del(array $keys): int|Pipeline|PhpRedis
    {
        return $this->client()->del(...$keys); // @phpstan-ignore return.type
    }

    /**
     * Increment the given members value.
     */
    public function zincrby(string $key, int $increment, string $member): string|float|Pipeline|PhpRedis
    {
        return $this->client()->zincrby($key, $increment, $member); // @phpstan-ignore return.type
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
            $this->client() instanceof Pipeline => $this->client()->xadd($key, $dictionary), // @phpstan-ignore method.notFound
        };
    }

    /**
     * Read a range of entries from the stream.
     *
     * @return array<string, array<string, string>>
     */
    public function xrange(string $key, string $start, string $end, int $count = null): array
    {
        return $this->client()->xrange(...array_filter(func_get_args())); // @phpstan-ignore method.notFound
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
            $this->client() instanceof Pipeline => $this->client()->xtrim($key, [$strategy, $strategyModifier], (string) $threshold), // @phpstan-ignore method.notFound
        };
    }

    /**
     * Delete the entries from the stream.
     *
     * @param  \Illuminate\Support\Collection<int, string>|array<int, string>  $keys
     */
    public function xdel(string $stream, Collection|array $keys): int
    {
        return $this->client()->xdel($stream, Collection::unwrap($keys)); // @phpstan-ignore method.notFound
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
        return $this->connection->pipeline(fn (Pipeline|PhpRedis $client) => $closure(new self($this->connection, $this->config, $client))); // @phpstan-ignore method.notFound
    }

    /**
     * Get the Redis client instance.
     */
    protected function client(): PhpRedis|Predis|Pipeline
    {
        return $this->client ?? $this->connection->client();
    }
}

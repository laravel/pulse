<?php

namespace Laravel\Pulse;

use Illuminate\Config\Repository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Collection;
use Laravel\Pulse\Exceptions\RedisVersionException;
use Predis\Client as Predis;
use Predis\Pipeline\Pipeline;
use Redis as PhpRedis;
use RuntimeException;
use Throwable;

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
        return $this->ensureVersionOnException('XADD', fn () => match (true) {
            $this->client() instanceof PhpRedis => $this->client()->xadd($key, '*', $dictionary),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->xadd($key, $dictionary), // @phpstan-ignore method.notFound
        });
    }

    /**
     * Read a range of entries from the stream.
     *
     * @return array<string, array<string, string>>
     */
    public function xrange(string $key, string $start, string $end, int $count = null): array
    {
        $args = array_filter(func_get_args());

        return $this->ensureVersionOnException(
            'XRANGE',
            fn () => $this->client()->xrange(...$args) // @phpstan-ignore method.notFound
        );
    }

    /**
     * Trim the stream.
     */
    public function xtrim(string $key, string $strategy, string $strategyModifier, string|int $threshold): int
    {
        return $this->ensureVersionOnException('XTRIM', fn () => match (true) {
            $this->client() instanceof PhpRedis => $this->client()->rawCommand('XTRIM', $this->config->get('database.redis.options.prefix').$key, $strategy, $strategyModifier, (string) $threshold),
            $this->client() instanceof Predis ||
            $this->client() instanceof Pipeline => $this->client()->xtrim($key, [$strategy, $strategyModifier], (string) $threshold), // @phpstan-ignore method.notFound
        });
    }

    /**
     * Delete the entries from the stream.
     *
     * @param  \Illuminate\Support\Collection<int, string>|array<int, string>  $keys
     */
    public function xdel(string $stream, Collection|array $keys): int
    {
        return $this->ensureVersionOnException(
            'XDEL',
            fn () => $this->client()->xdel($stream, Collection::unwrap($keys)) // @phpstan-ignore method.notFound
        );
    }

    /**
     * Run commands within a pipeline.
     *
     * @param  (callable(self): void)  $closure
     * @param  string|list<string>  $commands
     * @return array<int, mixed>
     */
    public function pipeline(callable $closure, string|array $commands): array
    {
        if ($this->client() instanceof Pipeline) {
            throw new RuntimeException('Pipelines are not able to be nested.');
        }

        // Create a pipeline and wrap the Redis client in an instance of this class to ensure our wrapper methods are used within the pipeline...
        return $this->ensureVersionOnException(
            $commands,
            fn () => $this->connection->pipeline(fn (Pipeline|PhpRedis $client) => $closure(new self($this->connection, $this->config, $client))) // @phpstan-ignore method.notFound
        );
    }

    /**
     * Get the Redis client instance.
     */
    protected function client(): PhpRedis|Predis|Pipeline
    {
        return $this->client ?? $this->connection->client();
    }

    /**
     * Ensure the version is supported if a command fails to run.
     *
     * @param  string|list<string>  $commands
     */
    protected function ensureVersionOnException(string|array $commands, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            try {
                $version = match (true) {
                    $this->client() instanceof PhpRedis => $this->client()->info('Server')['redis_version'],
                    $this->client() instanceof Predis ||
                    $this->client() instanceof Pipeline => $this->client()->info('Server')['Server']['redis_version'], //@phpstan-ignore offsetAccess.nonOffsetAccessible
                };
            } catch (Throwable) {
                throw $e;
            }

            if (version_compare($version, '6.2.0', '<')) {
                throw new RedisVersionException($commands, '6.2.0', $version);
            }

            throw $e;
        }
    }
}

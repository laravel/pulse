<?php

namespace Laravel\Pulse;

use Carbon\CarbonInterval as Interval;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\RedisManager;
use Predis\Client as Predis;
use Predis\Pipeline\Pipeline;
use Redis as PhpRedis;
use RuntimeException;

/**
 * @mixin \Redis
 * @mixin \Predis\Client
 *
 * @internal
 */
class Redis
{
    /**
     * Create a new Redis instance.
     *
     * @param  \Redis|\Predis\Client|\Predis\Pipeline\Pipeline|null  $client
     */
    public function __construct(protected array $config, protected ?RedisManager $manager = null, protected $client = null)
    {
        if ($manager === null && $client === null) {
            throw new RuntimeException('Must provider a manager or client.');
        }
    }

    /**
     * Add an entry to the stream.
     */
    public function xadd($key, $dictionary)
    {
        if ($this->isPhpRedis()) {
            return $this->client()->xAdd($key, '*', $dictionary);
        }

        return $this->client()->xAdd($key, $dictionary);
    }

    /**
     * Read a range of entries from the stream.
     */
    public function xrange($key, $start, $end, $count = null)
    {
        return $this->client()->xrange(...array_filter(func_get_args()));
    }

    /**
     * Trim the stream.
     */
    public function xtrim($key, $strategy, $strategyModifier, $threshold)
    {
        if ($this->isPhpRedis()) {
            // PHP Redis does not support the minid strategy.
            return $this->client()->rawCommand('XTRIM', $this->config['prefix'].$key, $strategy, $strategyModifier, $threshold);
        }

        return $this->client()->xtrim($key, [$strategy, $strategyModifier], $threshold);
    }

    /**
     * Run commands in a pipeline.
     */
    public function pipeline(callable $closure): array
    {
        // TODO explain this code - lol
        // ensure we run against a connection...
        return $this->connection()->pipeline(function ($redis) use ($closure) {
            $closure(new self($this->config, client: $redis));
        });
    }

    /**
     * Get the ID of the stream at a given time.
     */
    public function streamIdAt(Interval $interval): string
    {
        $redisTime = $this->client()->time();

        $redisTimestamp = $redisTime[0].substr($redisTime[1], 0, 3);

        return (string) ($redisTimestamp + $interval->totalMilliseconds);
    }

    /**
     * Determine if the client is PhpRedis.
     */
    protected function isPhpRedis(): bool
    {
        return $this->client() instanceof PhpRedis;
    }

    /**
     * Determine if the client is Predis.
     */
    protected function isPredis(): bool
    {
        return $this->client() instanceof Predis;
    }

    /**
     * The connections client.
     */
    protected function client(): PhpRedis|Predis|Pipeline
    {
        return $this->connection()?->client() ?? $this->client;
    }

    protected function connection(): ?Connection
    {
        return $this->manager?->connection($this->config['connection']);
    }

    /**
     * Proxies method calls to the connection or client.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return ($this->connection() ?? $this->client)->{$method}(...$parameters);
    }
}

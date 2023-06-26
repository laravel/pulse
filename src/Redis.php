<?php

namespace Laravel\Pulse;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Predis\Client as Predis;
use Redis as PhpRedis;

/**
 * @mixin \Redis
 * @mixin \Predis\Client
 */
class Redis
{
    /**
     * Create a new Redis instance.
     *
     * @param  \Redis|\Predis\Client  $client
     */
    public function __construct(protected $client)
    {
        //
    }

    public function expireat(string $key, int $timestamp, string $options)
    {
        $prefix = config('database.redis.options.prefix');

        if ($this->isPhpRedis()) {
            return $this->client->rawCommand('EXPIREAT', $prefix.$key, $timestamp, $options);
        }

        return $this->client->expireat($key, $timestamp, $options);
    }

    public function xadd($key, $dictionary)
    {
        if ($this->isPhpRedis()) {
            return $this->client->xAdd($key, '*', $dictionary);
        }

        return $this->client->xAdd($key, $dictionary);
    }

    public function xrange($key, $start, $end, $count = null)
    {
        if ($count) {
            return $this->client->xrange($key, $start, $end, $count);
        }

        return $this->client->xrange($key, $start, $end);
    }

    public function xrevrange($key, $end, $start, $count = null)
    {
        if ($count) {
            return $this->client->xrevrange($key, $end, $start, $count);
        }

        return $this->client->xrevrange($key, $end, $start);
    }

    public function xtrim($key, $strategy, $threshold)
    {
        $prefix = config('database.redis.options.prefix');

        if ($this->isPhpRedis()) {
            // PHP Redis does not support the minid strategy.
            return $this->client->rawCommand('XTRIM', $prefix.$key, $strategy, $threshold);
        }

        return $this->client->xtrim($key, $strategy, $threshold);
    }

    public function zadd($key, $score, $member, $options = null)
    {
        $prefix = config('database.redis.options.prefix');

        return match (true) {
            $this->isPhpRedis() && $options === null => $this->client->zAdd($key, $score, $member),
            $this->isPhpRedis() && $options !== null => $this->client->rawCommand('ZADD', $prefix.$key, $options, $score, $member),
            $this->isPredis() && $options === null => $this->client->zadd($key, [$member => $score]),
            $this->isPredis() && $options !== null => $this->client->executeRaw(['ZADD', $prefix.$key, $options, $score, $member]),
        };
    }

    public function zunionstore($destination, $keys, $aggregate = 'SUM')
    {
        if ($this->isPhpRedis()) {
            return $this->client->zUnionStore($destination, $keys, ['aggregate' => strtoupper($aggregate)]);
        }

        return $this->client->zunionstore($destination, $keys, [], strtolower($aggregate));
    }

    /**
     * Retrieve the time of the Redis server.
     */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->time()[0], 'UTC');
    }

    /**
     * Retrieve the oldest entry date for the given stream.
     */
    public function oldestStreamEntryDate(string $stream): ?CarbonImmutable
    {
        $key = array_key_first($this->xrange($stream, '-', '+', 1));

        if ($key === null) {
            return null;
        }

        return CarbonImmutable::createFromTimestampMs(Str::before($key, '-'), 'UTC')->startOfSecond();
    }

    /**
     * Determine if the client is PhpRedis.
     */
    protected function isPhpRedis(): bool
    {
        return $this->client instanceof PhpRedis;
    }

    /**
     * Determine if the client is Predis.
     */
    protected function isPredis(): bool
    {
        return $this->client instanceof Predis;
    }

    /**
     * Proxies all method calls to the client.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->client->{$method}(...$parameters);
    }
}

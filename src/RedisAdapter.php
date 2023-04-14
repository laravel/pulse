<?php

namespace Laravel\Pulse;

use Illuminate\Support\Facades\Redis;

class RedisAdapter
{
    public static function expireat(string $key, int $timestamp, string $options)
    {
        $prefix = config('database.redis.options.prefix');

        return match (true) {
            Redis::client() instanceof \Redis => Redis::rawCommand('EXPIREAT', $prefix.$key, $timestamp, $options),
            Redis::client() instanceof \Predis\Client => Redis::expireat($key, $timestamp, $options),
        };
    }

    public static function get($key)
    {
        return Redis::get($key);
    }

    public static function hgetall($key)
    {
        return Redis::hgetall($key);
    }

    public static function hset($key, $field, $value)
    {
        return Redis::hset($key, $field, $value);
    }

    public static function incr($key)
    {
        return Redis::incr($key);
    }

    public static function xadd($key, $dictionary)
    {
        return match (true) {
            Redis::client() instanceof \Redis => Redis::xAdd($key, '*', $dictionary),
            Redis::client() instanceof \Predis\Client => Redis::xadd($key, $dictionary),
        };
    }

    public static function xrange($key, $start, $end)
    {
        return Redis::xrange($key, $start, $end);
    }

    public static function xtrim($key, $threshold)
    {
        $prefix = config('database.redis.options.prefix');

        return match (true) {
            Redis::client() instanceof \Redis => Redis::xTrim($key, $threshold),
            // Predis currently doesn't apply the prefix on XTRIM commands.
            Redis::client() instanceof \Predis\Client => Redis::xtrim($prefix.$key, 'MAXLEN', $threshold),
        };
    }

    public static function zadd($key, $score, $member, $options = null)
    {
        $prefix = config('database.redis.options.prefix');

        return match (true) {
            Redis::client() instanceof \Redis && $options === null => Redis::zAdd($key, $score, $member),
            Redis::client() instanceof \Redis && $options !== null => Redis::rawCommand('ZADD', $prefix.$key, $options, $score, $member),
            Redis::client() instanceof \Predis\Client && $options === null => Redis::zadd($key, [$member => $score]),
            Redis::client() instanceof \Predis\Client && $options !== null => Redis::executeRaw(['ZADD', $prefix.$key, $options, $score, $member]),
        };
    }

    public static function zincrby($key, $increment, $member)
    {
        return Redis::zincrby($key, $increment, $member);
    }

    public static function zrevrange($key, $start, $stop, $withScores = false)
    {
        return Redis::zrevrange($key, $start, $stop, ['WITHSCORES' => $withScores]);
    }

    public static function zunionstore($destination, $keys, $aggregate = 'SUM')
    {
        return match (true) {
            Redis::client() instanceof \Redis => Redis::zUnionStore($destination, $keys, ['aggregate' => strtoupper($aggregate)]),
            Redis::client() instanceof \Predis\Client => Redis::zunionstore($destination, $keys, [], strtolower($aggregate)),
        };
    }
}

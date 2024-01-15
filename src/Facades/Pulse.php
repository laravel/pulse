<?php

namespace Laravel\Pulse\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Pulse\Pulse register(array $recorders)
 * @method static \Laravel\Pulse\Entry record(string $type, string $key, int|null $value = null, \DateTimeInterface|int|null $timestamp = null)
 * @method static \Laravel\Pulse\Value set(string $type, string $key, string $value, \DateTimeInterface|int|null $timestamp = null)
 * @method static \Laravel\Pulse\Pulse lazy(callable $closure)
 * @method static \Laravel\Pulse\Pulse report(\Throwable $e)
 * @method static \Laravel\Pulse\Pulse startRecording()
 * @method static \Laravel\Pulse\Pulse stopRecording()
 * @method static mixed ignore(callable $callback)
 * @method static \Laravel\Pulse\Pulse flush()
 * @method static \Laravel\Pulse\Pulse filter(callable $filter)
 * @method static int ingest()
 * @method static int digest()
 * @method static bool wantsIngesting()
 * @method static \Illuminate\Support\Collection recorders()
 * @method static \Laravel\Pulse\Contracts\ResolvesUsers resolveUsers(\Illuminate\Support\Collection $keys)
 * @method static \Laravel\Pulse\Pulse user(callable $callback)
 * @method static callable authenticatedUserIdResolver()
 * @method static string|int|null resolveAuthenticatedUserId()
 * @method static \Laravel\Pulse\Pulse rememberUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \Laravel\Pulse\Pulse|string css(string|\Illuminate\Contracts\Support\Htmlable|array|null $css = null)
 * @method static string js()
 * @method static array defaultVendorCacheKeys()
 * @method static bool registersRoutes()
 * @method static \Laravel\Pulse\Pulse ignoreRoutes()
 * @method static \Laravel\Pulse\Pulse handleExceptionsUsing(callable $callback)
 * @method static mixed rescue(callable $callback)
 * @method static \Laravel\Pulse\Pulse setContainer(\Illuminate\Contracts\Foundation\Application $container)
 * @method static void afterResolving(\Illuminate\Contracts\Foundation\Application $app, string $class, \Closure $callback)
 * @method static void store(\Illuminate\Support\Collection $items)
 * @method static void trim()
 * @method static void purge(array $types = null)
 * @method static \Illuminate\Support\Collection values(string $type, array $keys = null)
 * @method static \Illuminate\Support\Collection graph(array $types, string $aggregate, \Carbon\CarbonInterval $interval)
 * @method static \Illuminate\Support\Collection aggregate(string $type, string|array $aggregates, \Carbon\CarbonInterval $interval, string|null $orderBy = null, string $direction = 'desc', int $limit = 101)
 * @method static \Illuminate\Support\Collection aggregateTypes(string|array $types, string $aggregate, \Carbon\CarbonInterval $interval, string|null $orderBy = null, string $direction = 'desc', int $limit = 101)
 * @method static float|\Illuminate\Support\Collection aggregateTotal(string|array $types, string $aggregate, \Carbon\CarbonInterval $interval)
 *
 * @see \Laravel\Pulse\Pulse
 */
class Pulse extends Facade
{
    /**
     * Get the registered name of the component.
     */
    public static function getFacadeAccessor(): string
    {
        return \Laravel\Pulse\Pulse::class;
    }
}

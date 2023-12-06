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
 * @method static mixed|null ignore(callable $callback)
 * @method static \Laravel\Pulse\Pulse flush()
 * @method static \Laravel\Pulse\Pulse filter(callable $filter)
 * @method static int store()
 * @method static \Illuminate\Support\Collection recorders()
 * @method static \Illuminate\Support\Collection resolveUsers(\Illuminate\Support\Collection $ids)
 * @method static \Laravel\Pulse\Pulse users(callable $callback)
 * @method static callable authenticatedUserIdResolver()
 * @method static string|int|null resolveAuthenticatedUserId()
 * @method static \Laravel\Pulse\Pulse resolveAuthenticatedUserIdUsing(callable $callback)
 * @method static mixed|null withUser(\Illuminate\Contracts\Auth\Authenticatable|string|int|null $user, callable $callback)
 * @method static \Laravel\Pulse\Pulse rememberUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static string|self css(array|string|null $path = null)
 * @method static string js()
 * @method static bool registersRoutes()
 * @method static \Laravel\Pulse\Pulse ignoreRoutes()
 * @method static \Laravel\Pulse\Pulse handleExceptionsUsing(callable $callback)
 * @method static void rescue(callable $callback)
 * @method static \Laravel\Pulse\Pulse setContainer(\Illuminate\Contracts\Foundation\Application $container)
 * @method static void afterResolving(\Illuminate\Contracts\Foundation\Application $app, string $class, \Closure $callback)
 * @method static void trim()
 * @method static void purge(array $types = null)
 * @method static \Illuminate\Support\Collection values(string $type, array $keys = null)
 * @method static \Illuminate\Support\Collection graph(array $types, string $aggregate, \Carbon\CarbonInterval $interval)
 * @method static \Illuminate\Support\Collection aggregate(string $type, array $aggregates, \Carbon\CarbonInterval $interval, string|null $orderBy = null, string $direction = 'desc', int $limit = 101)
 * @method static \Illuminate\Support\Collection aggregateTypes(string|array $types, string $aggregate, \Carbon\CarbonInterval $interval, string|null $orderBy = null, string $direction = 'desc', int $limit = 101)
 * @method static \Illuminate\Support\Collection aggregateTotal(string|array $types, string $aggregate, \Carbon\CarbonInterval $interval)
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

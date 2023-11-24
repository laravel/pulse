<?php

namespace Laravel\Pulse\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Pulse\Pulse register(array $recorders)
 * @method static \Laravel\Pulse\Pulse record(\Laravel\Pulse\Entry $entry)
 * @method static \Laravel\Pulse\Pulse report(\Throwable $e)
 * @method static \Laravel\Pulse\Pulse startRecording()
 * @method static \Laravel\Pulse\Pulse stopRecording()
 * @method static mixed|null ignore(callable $callback)
 * @method static \Illuminate\Support\Collection entries()
 * @method static \Laravel\Pulse\Pulse flushEntries()
 * @method static \Laravel\Pulse\Pulse filter(callable $filter)
 * @method static \Laravel\Pulse\Pulse store(\Laravel\Pulse\Contracts\Ingest $ingest)
 * @method static \Illuminate\Support\Collection recorders()
 * @method static \Illuminate\Support\Collection resolveUsers(\Illuminate\Support\Collection $ids)
 * @method static \Laravel\Pulse\Pulse resolveUsersUsing(callable $callback)
 * @method static callable authenticatedUserIdResolver()
 * @method static \Laravel\Pulse\Pulse resolveAuthenticatedUserIdUsing(callable $callback)
 * @method static mixed|null withUser(\Illuminate\Contracts\Auth\Authenticatable|string|int|null $user, callable $callback)
 * @method static \Laravel\Pulse\Pulse rememberUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static string css()
 * @method static string js()
 * @method static bool runsMigrations()
 * @method static \Laravel\Pulse\Pulse ignoreMigrations()
 * @method static \Laravel\Pulse\Pulse handleExceptionsUsing(callable $callback)
 * @method static void rescue(callable $callback)
 * @method static void afterResolving(\Illuminate\Foundation\Application $app, string $class, \Closure $callback)
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

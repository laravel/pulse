<?php

namespace Laravel\Pulse\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed|null ignore(callable $callback)
 * @method static \Laravel\Pulse\Pulse stopRecording()
 * @method static \Laravel\Pulse\Pulse startRecording()
 * @method static \Laravel\Pulse\Pulse filter(callable $filter)
 * @method static \Laravel\Pulse\Pulse record(\Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update $entry)
 * @method static \Laravel\Pulse\Pulse store()
 * @method static \Illuminate\Support\Collection queue()
 * @method static \Laravel\Pulse\Pulse flushQueue()
 * @method static \Laravel\Pulse\Pulse resolveApplicationUsageUsersUsing(callable $callback)
 * @method static \Illuminate\Support\Collection resolveApplicationUsageUsers(\Illuminate\Support\Collection $ids)
 * @method static string css()
 * @method static string js()
 * @method static bool authorize(\Illuminate\Http\Request $request)
 * @method static \Laravel\Pulse\Pulse authorizeUsing(callable $callback)
 * @method static \Laravel\Pulse\Pulse ignoreMigrations()
 * @method static bool runsMigrations()
 * @method static \Laravel\Pulse\Pulse handleExceptionsUsing(callable $callback)
 * @method static \Laravel\Pulse\Pulse resolveAuthenticatedUserIdUsing(callable $callback)
 * @method static callable authenticatedUserIdResolver()
 * @method static mixed|null withUser(\Illuminate\Contracts\Auth\Authenticatable|string|int|null $user, callable $callback)
 * @method static \Laravel\Pulse\Pulse rememberUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static void rescue(callable $callback)
 * @method static void listenForStorageOpportunities(\Illuminate\Foundation\Application $app)
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

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
 * @method static \Laravel\Pulse\Pulse resolveUsersUsing(callable $callback)
 * @method static \Illuminate\Support\Collection resolveUsers(\Illuminate\Support\Collection $ids)
 * @method static string css()
 * @method static string js()
 * @method static bool authorize(\Illuminate\Http\Request $request)
 * @method static \Laravel\Pulse\Pulse auth(callable $callback)
 * @method static \Laravel\Pulse\Pulse ignoreMigrations()
 * @method static bool runsMigrations()
 * @method static \Laravel\Pulse\Pulse handleExceptionsUsing(callable $callback)
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

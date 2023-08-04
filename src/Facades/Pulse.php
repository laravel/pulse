<?php

namespace Laravel\Pulse\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Pulse\Pulse shouldNotRecord()
 * @method static void filter(callable $filter)
 * @method static \Laravel\Pulse\Pulse resolveUsersUsing(void $callback)
 * @method static \Illuminate\Support\Collection resolveUsers(\Illuminate\Support\Collection $ids)
 * @method static void record(\Laravel\Pulse\Entries\Entry $entry)
 * @method static void recordUpdate(\Laravel\Pulse\Entries\Update $update)
 * @method static void store()
 * @method static string css()
 * @method static string js()
 * @method static bool check(\Illuminate\Http\Request $request)
 * @method static \Laravel\Pulse\Pulse auth(\Closure $callback)
 * @method static \Laravel\Pulse\Pulse ignoreMigrations()
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

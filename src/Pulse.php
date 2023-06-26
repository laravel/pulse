<?php

namespace Laravel\Pulse;

use Closure;
use Illuminate\Support\Facades\DB;

class Pulse
{
    use ListensForStorageOpportunities;

    /**
     * The callback that should be used to authenticate Pulse users.
     *
     * @var \Closure
     */
    public static $authUsing;

    /**
     * Indicates if Pulse migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * The list of queued entries to be stored.
     *
     * @var array
     */
    public $entriesQueue = [];

    /**
     * The list of queued entry updates.
     *
     * @var array
     */
    public $updatesQueue = [];

    /**
     * Indicates if Pulse should record entries.
     *
     * @var bool
     */
    public $shouldRecord = true;

    /**
     * Record the given entry.
     *
     * @param  string  $table
     * @param  array  $attributes
     * @return void
     */
    public function record($table, $attributes)
    {
        if ($this->shouldRecord) {
            $this->entriesQueue[$table][] = $attributes;
        }
    }

    /**
     * Record the given entry update.
     *
     * @param  mixed  $update
     * @return void
     */
    public function recordUpdate($update)
    {
        if ($this->shouldRecord) {
            $this->updatesQueue[] = $update;
        }
    }

    /**
     * Store the queued entries and flush the queue.
     *
     * @return void
     */
    public function store()
    {
        // TODO: Prevent these entries from being recorded?
        foreach ($this->entriesQueue as $table => $rows) {
            DB::table($table)->insert($rows);
        }

        foreach ($this->updatesQueue as $update) {
            $update->perform();
        }

        $this->entriesQueue = [];
        $this->updatesQueue = [];
    }

    /**
     * Return the compiled CSS from the vendor directory.
     *
     * @return string
     */
    public function css()
    {
        return file_get_contents(__DIR__.'/../dist/pulse.css');
    }

    /**
     * Return the compiled JavaScript from the vendor directory.
     *
     * @return string
     */
    public function js()
    {
        return file_get_contents(__DIR__.'/../dist/pulse.js');
    }

    /**
     * Determine if the given request can access the Pulse dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function check($request)
    {
        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authorize Pulse users.
     *
     * @return static
     */
    public static function auth(Closure $callback)
    {
        static::$authUsing = $callback;

        return new static;
    }

    /**
     * Configure Pulse to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }
}

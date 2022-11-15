<?php

namespace Laravel\Pulse;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PulseServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // $this->mergeConfigFrom(
        //     __DIR__.'/../config/pulse.php', 'pulse'
        // );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        //
    }

    /**
     * Register the package resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse');
    }

    /**
     * Register the package's migrations.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        // if ($this->app->runningInConsole() && Pulse::$runsMigrations) {
        //     $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // }
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            // $this->publishes([
            //     __DIR__.'/../database/migrations' => database_path('migrations'),
            // ], 'pulse-migrations');

            // $this->publishes([
            //     __DIR__.'/../public' => public_path('vendor/pulse'),
            // ], ['pulse-assets', 'laravel-assets']);

            $this->publishes([
                __DIR__.'/../config/pulse.php' => config_path('pulse.php'),
            ], 'pulse-config');
        }
    }

    /**
     * Register the package's commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                //
            ]);
        }
    }
}

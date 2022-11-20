<?php

namespace Laravel\Pulse;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Commands\CheckCommand;
use Laravel\Pulse\Handlers\HandleCacheHit;
use Laravel\Pulse\Handlers\HandleCacheMiss;
use Laravel\Pulse\Handlers\HandleHttpRequest;
use Laravel\Pulse\Handlers\HandleLogMessage;
use Laravel\Pulse\Handlers\HandleQuery;
use Throwable;

class PulseServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        // $this->mergeConfigFrom(
        //     __DIR__.'/../config/pulse.php', 'pulse'
        // );

        $this->listenForEvents();
    }

    /**
     * Listen for the events that are relevant to the package.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $this->app->make(Kernel::class)
            ->whenRequestLifecycleIsLongerThan(0, function ($startedAt, $request, $response) {
                (new HandleHttpRequest)($startedAt, $request, $response);
            });

        DB::listen(fn ($e) => (new HandleQuery)($e));

        $this->app->make(ExceptionHandler::class)
            ->reportable(function (Throwable $e) {
                (new HandleException)($e);
            });

        Event::listen(MessageLogged::class, HandleLogMessage::class);
        Event::listen(CacheHit::class, HandleCacheHit::class);
        Event::listen(CacheMissed::class, HandleCacheMiss::class);
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
                CheckCommand::class,
            ]);
        }
    }
}

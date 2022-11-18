<?php

namespace Laravel\Pulse;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Routing\RouteAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Pulse\Commands\CheckCommand;
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
        $this->app->make(Kernel::class)->whenRequestLifecycleIsLongerThan(0, function ($startedAt, $request, $response) {
            ray('Request Duration: '.$startedAt->diffInMilliseconds(now()).'ms');

            $action = $request->route()?->getAction();
            $hasController = $action && is_string($action['uses']) && ! RouteAction::containsSerializedClosure($action);

            ray('Route Path: '.$request->route()?->uri());

            if ($hasController) {
                $parsedAction = Str::parseCallback($action['uses']);
                ray('Route Controller: '.$parsedAction[0].'@'.$parsedAction[1]);
            }
        });

        DB::listen(function ($e) {
            ray('Query Duration: '.$e->time);
        });

        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) {
            ray('Received Exception...');
        });

        Event::listen(function (MessageLogged $e) {
            ray('Message Logged: '.$e->message);
        });

        Event::listen(function (CacheHit $e) {
            ray('Cache Hit: '.$e->key);
        });

        Event::listen(function (CacheMissed $e) {
            ray('Cache Miss: '.$e->key);
        });
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

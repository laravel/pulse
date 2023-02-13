<?php

namespace Laravel\Pulse;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Commands\CheckCommand;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Handlers\HandleCacheHit;
use Laravel\Pulse\Handlers\HandleCacheMiss;
use Laravel\Pulse\Handlers\HandleException;
use Laravel\Pulse\Handlers\HandleHttpRequest;
use Laravel\Pulse\Handlers\HandleLogMessage;
use Laravel\Pulse\Handlers\HandleQuery;
use Laravel\Pulse\Http\Livewire\Cache;
use Laravel\Pulse\Http\Livewire\Exceptions;
use Laravel\Pulse\Http\Livewire\Queues;
use Laravel\Pulse\Http\Livewire\Servers;
use Laravel\Pulse\Http\Livewire\SlowEndpoints;
use Laravel\Pulse\Http\Livewire\SlowQueries;
use Laravel\Pulse\Http\Livewire\Usage;
use Laravel\Pulse\View\Components\Pulse as PulseComponent;
use Livewire\Livewire;
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

        $this->app->singleton(Pulse::class);

        $this->mergeConfigFrom(
            __DIR__.'/../config/pulse.php', 'pulse'
        );

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

        //Event::listen(MessageLogged::class, HandleLogMessage::class);
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
        Livewire::listen('component.boot', function ($instance) {
            if ($instance instanceof ShouldNotReportUsage) {
                $this->app->make(Pulse::class)->doNotReportUsage = true;
            }
        });

        $this->registerRoutes();
        $this->registerResources();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerComponents();
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        Route::get(config('pulse.path'), function () {
            $this->app->make(Pulse::class)->doNotReportUsage = true;

            return view('pulse::dashboard');
        })->middleware(config('pulse.middleware'));
    }

    /**
     * Register the package resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse');
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

            $this->publishes([
                __DIR__.'/../resources/views/dashboard.blade.php' => resource_path('views/vendor/pulse/dashboard.blade.php'),
            ], 'pulse-dashboard');
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

    /**
     * Register the package's components.
     *
     * @return void
     */
    protected function registerComponents()
    {
        Blade::component('pulse', PulseComponent::class);

        Livewire::component('servers', Servers::class);
        Livewire::component('usage', Usage::class);
        Livewire::component('exceptions', Exceptions::class);
        Livewire::component('slow-endpoints', SlowEndpoints::class);
        Livewire::component('slow-queries', SlowQueries::class);
        Livewire::component('cache', Cache::class);
        Livewire::component('queues', Queues::class);
    }
}

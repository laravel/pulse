<?php

namespace Laravel\Pulse;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Commands\CheckCommand;
use Laravel\Pulse\Commands\WorkCommand;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Handlers\HandleCacheInteraction;
use Laravel\Pulse\Handlers\HandleException;
use Laravel\Pulse\Handlers\HandleHttpRequest;
use Laravel\Pulse\Handlers\HandleProcessedJob;
use Laravel\Pulse\Handlers\HandleProcessingJob;
use Laravel\Pulse\Handlers\HandleQuery;
use Laravel\Pulse\Handlers\HandleQueuedJob;
use Laravel\Pulse\Handlers\HttpRequestMiddleware;
use Laravel\Pulse\Http\Livewire\Cache;
use Laravel\Pulse\Http\Livewire\Exceptions;
use Laravel\Pulse\Http\Livewire\PeriodSelector;
use Laravel\Pulse\Http\Livewire\Queues;
use Laravel\Pulse\Http\Livewire\Servers;
use Laravel\Pulse\Http\Livewire\SlowJobs;
use Laravel\Pulse\Http\Livewire\SlowOutgoingRequests;
use Laravel\Pulse\Http\Livewire\SlowQueries;
use Laravel\Pulse\Http\Livewire\SlowRoutes;
use Laravel\Pulse\Http\Livewire\Usage;
use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\View\Components\Pulse as PulseComponent;
use Livewire\Livewire;
use Throwable;

class PulseServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $this->app->scoped(Pulse::class);

        $this->app->scoped(Redis::class, fn () => new Redis(app('redis')->connection()->client()));

        $this->mergeConfigFrom(
            __DIR__.'/../config/pulse.php', 'pulse'
        );

        // TODO: this should be moved to "boot" once / if https://github.com/nunomaduro/collision/pull/276/
        // is merged and tagged. We should also set the minimum version of Collision
        // in our composer.json
        $this->app[ExceptionHandler::class]
            ->reportable(fn (Throwable $e) => app(HandleException::class)($e));
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $this->listenForEvents();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerComponents();
    }

    /**
     * Listen for the events that are relevant to the package.
     */
    protected function listenForEvents(): void
    {
        Livewire::listen('component.boot', function ($instance) {
            if ($instance instanceof ShouldNotReportUsage) {
                app(Pulse::class)->shouldRecord = false;
            }
        });

        $this->app[Kernel::class]
            ->whenRequestLifecycleIsLongerThan(0, fn (...$args) => app(HandleHttpRequest::class)(...$args));

        Event::listen(QueryExecuted::class, HandleQuery::class);

        Event::listen([
            CacheHit::class,
            CacheMissed::class,
        ], HandleCacheInteraction::class);

        // TODO: handle other job events, such as failing.
        Event::listen(JobQueued::class, HandleQueuedJob::class);
        Event::listen(JobProcessing::class, HandleProcessingJob::class);
        Event::listen(JobProcessed::class, HandleProcessedJob::class);

        if (method_exists(Factory::class, 'globalMiddleware')) {
            Http::globalMiddleware(fn ($handler) => app(HttpRequestMiddleware::class)($handler));
        }

        // TODO: Telescope passes the container like this, but I'm unsure how it works with Octane.
        // TODO: consider moving this to the "Booted" event to ensure, for sure, that our stuff is registered last?
        Pulse::listenForStorageOpportunities($this->app);
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'domain' => config('pulse.domain', null),
            'middleware' => config('pulse.middleware', 'web'),
            'namespace' => 'Laravel\Pulse\Http\Controllers',
            'prefix' => config('pulse.path'),
        ], function () {
            Route::get('/', function (Pulse $pulse) {
                $pulse->shouldRecord = false;

                return view('pulse::dashboard');
            });
        });
    }

    /**
     * Register the package resources.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse');
    }

    /**
     * Register the package's migrations.
     */
    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole() && Pulse::$runsMigrations) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
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
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckCommand::class,
                WorkCommand::class,
            ]);
        }
    }

    /**
     * Register the package's components.
     */
    protected function registerComponents(): void
    {
        Blade::component('pulse', PulseComponent::class);

        Livewire::addPersistentMiddleware([
            Authorize::class,
        ]);

        Livewire::component('period-selector', PeriodSelector::class);
        Livewire::component('servers', Servers::class);
        Livewire::component('usage', Usage::class);
        Livewire::component('exceptions', Exceptions::class);
        Livewire::component('slow-routes', SlowRoutes::class);
        Livewire::component('slow-queries', SlowQueries::class);
        Livewire::component('slow-jobs', SlowJobs::class);
        Livewire::component('slow-outgoing-requests', SlowOutgoingRequests::class);
        Livewire::component('cache', Cache::class);
        Livewire::component('queues', Queues::class);
    }
}

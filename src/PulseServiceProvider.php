<?php

namespace Laravel\Pulse;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Commands\CheckCommand;
use Laravel\Pulse\Commands\RestartCommand;
use Laravel\Pulse\Commands\WorkCommand;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Handlers\HandleCacheInteraction;
use Laravel\Pulse\Handlers\HandleException;
use Laravel\Pulse\Handlers\HandleHttpRequest;
use Laravel\Pulse\Handlers\HandleOutgoingRequest;
use Laravel\Pulse\Handlers\HandleProcessedJob;
use Laravel\Pulse\Handlers\HandleProcessingJob;
use Laravel\Pulse\Handlers\HandleQuery;
use Laravel\Pulse\Handlers\HandleQueuedJob;
use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\Ingests\Redis as RedisIngest;
use Laravel\Pulse\Ingests\Storage as StorageIngest;
use Laravel\Pulse\Livewire\Cache;
use Laravel\Pulse\Livewire\Exceptions;
use Laravel\Pulse\Livewire\PeriodSelector;
use Laravel\Pulse\Livewire\Queues;
use Laravel\Pulse\Livewire\Servers;
use Laravel\Pulse\Livewire\SlowJobs;
use Laravel\Pulse\Livewire\SlowOutgoingRequests;
use Laravel\Pulse\Livewire\SlowQueries;
use Laravel\Pulse\Livewire\SlowRoutes;
use Laravel\Pulse\Livewire\Usage;
use Laravel\Pulse\Storage\Database;
use Laravel\Pulse\View\Components\Pulse as PulseComponent;
use Livewire\Livewire;

class PulseServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        if (! $this->app['config']->get('pulse.enabled', true) || $this->app->runningUnitTests()) {
            return;
        }

        $this->app->singleton(Pulse::class);

        $this->app->bind(Storage::class, Database::class);

        $this->app->bind(Ingest::class, fn ($app) => $app['config']->get('pulse.ingest.driver') === 'storage'
            ? $app[StorageIngest::class]
            : $app[RedisIngest::class]);

        foreach ([
            Queries\Servers::class,
            Queries\Usage::class,
            Queries\Exceptions::class,
            Queries\SlowRoutes::class,
            Queries\SlowQueries::class,
            Queries\SlowJobs::class,
            Queries\SlowOutgoingRequests::class,
        ] as $class) {
            $this->app->when($class)
                ->needs(Connection::class)
                ->give(fn ($app) => $app['db']->connection($app['config']->get(
                    "pulse.storage.{$app['config']->get('pulse.storage.driver')}.connection"
                )));
        }

        foreach ([
            Servers::class => Queries\Servers::class,
            Usage::class => Queries\Usage::class,
            Exceptions::class => Queries\Exceptions::class,
            SlowRoutes::class => Queries\SlowRoutes::class,
            SlowQueries::class => Queries\SlowQueries::class,
            SlowJobs::class => Queries\SlowJobs::class,
            SlowOutgoingRequests::class => Queries\SlowOutgoingRequests::class,
        ] as $card => $query) {
            $this->app->bindMethod([$card, 'render'], fn ($instance, $app) => $instance->render($app[$query]));
        }

        $this->mergeConfigFrom(
            __DIR__.'/../config/pulse.php', 'pulse'
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if (! $this->app['config']->get('pulse.enabled', true) || $this->app->runningUnitTests()) {
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
        $this->app[Kernel::class]->whenRequestLifecycleIsLongerThan(0, new HandleHttpRequest);

        $this->app[ExceptionHandler::class]->reportable(new HandleException);

        Event::listen(QueryExecuted::class, HandleQuery::class);

        Event::listen([
            CacheHit::class,
            CacheMissed::class,
        ], HandleCacheInteraction::class);

        // TODO: currently if a job fails, we have no way of tracking it through properly.
        // When a job fails it gets a new "jobId", so we can't track the one job.
        // If we can get the job's UUID in the `JobQueued` event, then we can
        // follow the job through successfully.
        Event::listen(JobQueued::class, HandleQueuedJob::class);
        Event::listen(JobProcessing::class, HandleProcessingJob::class);
        Event::listen([
            JobProcessed::class,
            JobFailed::class,
        ], HandleProcessedJob::class);

        if (method_exists(Factory::class, 'globalMiddleware')) {
            Http::globalMiddleware(new HandleOutgoingRequest);
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
            'domain' => $this->app['config']->get('pulse.domain', null),
            'middleware' => $this->app['config']->get('pulse.middleware', 'web'),
            'prefix' => $this->app['config']->get('pulse.path'),
        ], fn () => Route::get('/', function (Pulse $pulse) {
            $pulse->shouldNotRecord();

            return view('pulse::dashboard');
        }));
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
        // TODO: don't resolve Pulse here
        if ($this->app->runningInConsole() && app(Pulse::class)->runsMigrations()) {
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
                RestartCommand::class,
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

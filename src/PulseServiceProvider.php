<?php

namespace Laravel\Pulse;

use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Factory as ViewFactory;
use Laravel\Pulse\Commands\CheckCommand;
use Laravel\Pulse\Commands\RestartCommand;
use Laravel\Pulse\Commands\WorkCommand;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\Ingests\Redis as RedisIngest;
use Laravel\Pulse\Ingests\Storage as StorageIngest;
use Laravel\Pulse\Recorders\CacheInteractions;
use Laravel\Pulse\Recorders\Exceptions;
use Laravel\Pulse\Recorders\HttpRequests;
use Laravel\Pulse\Recorders\Jobs;
use Laravel\Pulse\Recorders\OutgoingRequests;
use Laravel\Pulse\Recorders\SlowQueries;
use Laravel\Pulse\Storage\Database as DatabaseStorage;
use Laravel\Pulse\View\Components\Pulse as PulseComponent;
use Livewire\Component;
use Livewire\LivewireManager;
use RuntimeException;

class PulseServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/pulse.php', 'pulse'
        );

        if (! $this->app['config']->get('pulse.enabled')) {
            return;
        }

        $this->app->singleton(Pulse::class);

        $this->app->bind(Storage::class, DatabaseStorage::class);

        $this->app->bind(Ingest::class, fn (Application $app) => match ($app['config']->get('pulse.ingest.driver')) {
            'storage' => $app[StorageIngest::class],
            'redis' => $app[RedisIngest::class],
            default => throw new RuntimeException("Unknown ingest driver [{$app['config']->get('pulse.ingest.driver')}]."),
        });

        $this->app->bindMethod([CheckCommand::class, 'handle'], function (CheckCommand $instance, Application $app) {
            $checks = collect($app['config']->get('pulse.checks'))->map(fn (string $check) => $app->make($check));

            return $instance->handle($app[Pulse::class], $app[Ingest::class], $app['cache'], $checks);
        });

        foreach ([
            Queries\Usage::class,
            Queries\Servers::class,
            Queries\SlowJobs::class,
            Queries\Exceptions::class,
            Queries\SlowRoutes::class,
            Queries\SlowQueries::class,
            Queries\CacheInteractions::class,
            Queries\SlowOutgoingRequests::class,
            Queries\MonitoredCacheInteractions::class,
        ] as $class) {
            // TODO: these should get the databasemanager and confing
            $this->app->when($class)
                ->needs(Connection::class)
                ->give(fn (Application $app) => $app['db']->connection($app['config']->get(
                    'pulse.storage.database.connection'
                )));
        }

        foreach ([
            Livewire\Usage::class => [Queries\Usage::class],
            Livewire\Queues::class => [Queries\Queues::class],
            Livewire\Servers::class => [Queries\Servers::class],
            Livewire\SlowJobs::class => [Queries\SlowJobs::class],
            Livewire\Exceptions::class => [Queries\Exceptions::class],
            Livewire\SlowRoutes::class => [Queries\SlowRoutes::class],
            Livewire\SlowQueries::class => [Queries\SlowQueries::class],
            Livewire\SlowOutgoingRequests::class => [Queries\SlowOutgoingRequests::class],
            Livewire\Cache::class => [Queries\CacheInteractions::class, Queries\MonitoredCacheInteractions::class],
        ] as $card => $queries) {
            $this->app->bindMethod([$card, 'render'], fn (Component $instance, Application $app) => $instance->render(...array_map($app->make(...), $queries)));
        }
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if (! $this->app['config']->get('pulse.enabled')) {
            return;
        }

        $pulse = $this->app[Pulse::class];

        $pulse->register([
            CacheInteractions::class,
            Exceptions::class,
            HttpRequests::class,
            Jobs::class,
            OutgoingRequests::class,
            SlowQueries::class,
        ]);

        $this->registerRoutes();
        $this->listenForEvents();
        $this->registerCommands();
        $this->registerResources();
        $this->registerComponents();
        $this->registerMigrations();
        $this->registerPublishing();
    }

    /**
     * Listen for the events that are relevant to the package.
     */
    protected function listenForEvents(): void
    {
        $this->callAfterResolving('events', function (Dispatcher $event) {
            $event->listen(Logout::class, function (Logout $event) {
                $pulse = app(Pulse::class);
                $pulse->rescue(fn () => $pulse->rememberUser($event->user));
            });
        });

        // TODO: Telescope passes the container like this, but I'm unsure how it works with Octane.
        // TODO: consider moving this to the "Booted" event to ensure, for sure, that our stuff is registered last?
        Pulse::listenForStorageOpportunities($this->app);
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $this->callAfterResolving('router', function (Router $router, Application $app) {
            $router->group([
                'domain' => $app['config']->get('pulse.domain', null),
                'middleware' => $app['config']->get('pulse.middleware', 'web'),
                'prefix' => $app['config']->get('pulse.path'),
            ], fn (Router $router) => $router->get('/', function (Pulse $pulse, ViewFactory $view) {
                $pulse->stopRecording();

                return $view->make('pulse::dashboard');
            }));
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
        $this->callAfterResolving('migrator', function (Migrator $migrator, Application $app) {
            if ($app[Pulse::class]->runsMigrations()) {
                $migrator->path(__DIR__.'/../database/migrations');
            }
        });
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/pulse.php' => config_path('pulse.php'),
            ], ['pulse', 'pulse-config']);

            $this->publishes([
                __DIR__.'/../resources/views/dashboard.blade.php' => resource_path('views/vendor/pulse/dashboard.blade.php'),
            ], ['pulse', 'pulse-dashboard']);
        }
    }

    /**
     * Register the package's commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                WorkCommand::class,
                CheckCommand::class,
                RestartCommand::class,
            ]);
        }
    }

    /**
     * Register the package's components.
     */
    protected function registerComponents(): void
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade) {
            $blade->component('pulse', PulseComponent::class);
        });

        $this->callAfterResolving('livewire', function (LivewireManager $livewire) {
            $livewire->addPersistentMiddleware([Authorize::class]);

            $livewire->component('cache', Livewire\Cache::class);
            $livewire->component('usage', Livewire\Usage::class);
            $livewire->component('queues', Livewire\Queues::class);
            $livewire->component('servers', Livewire\Servers::class);
            $livewire->component('slow-jobs', Livewire\SlowJobs::class);
            $livewire->component('exceptions', Livewire\Exceptions::class);
            $livewire->component('slow-routes', Livewire\SlowRoutes::class);
            $livewire->component('slow-queries', Livewire\SlowQueries::class);
            $livewire->component('period-selector', Livewire\PeriodSelector::class);
            $livewire->component('slow-outgoing-requests', Livewire\SlowOutgoingRequests::class);
        });
    }
}

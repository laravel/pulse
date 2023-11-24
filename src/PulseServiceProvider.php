<?php

namespace Laravel\Pulse;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Factory as ViewFactory;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Ingests\Redis as RedisIngest;
use Laravel\Pulse\Ingests\Storage as StorageIngest;
use Laravel\Pulse\Storage\Database as DatabaseStorage;
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

        if (! $this->app['config']->get('pulse.enabled')) { // @phpstan-ignore offsetAccess.nonOffsetAccessible
            return;
        }

        $this->app->singleton(Pulse::class);
        $this->app->bind(Storage::class, DatabaseStorage::class);

        $this->registerIngest();
    }

    /**
     * Register the ingest implementation.
     */
    protected function registerIngest(): void
    {
        $this->app->bind(Ingest::class, fn (Application $app) => match ($app['config']->get('pulse.ingest.driver')) {
            'storage' => $app[StorageIngest::class],
            'redis' => $app[RedisIngest::class],
            default => throw new RuntimeException("Unknown ingest driver [{$app['config']->get('pulse.ingest.driver')}]."),
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if (! $this->app['config']->get('pulse.enabled')) { // @phpstan-ignore offsetAccess.nonOffsetAccessible
            return;
        }

        $this->app[Pulse::class]->register($this->app['config']->get('pulse.recorders')); // @phpstan-ignore offsetAccess.nonOffsetAccessible offsetAccess.nonOffsetAccessible

        $this->registerAuthorization();
        $this->registerRoutes();
        $this->listenForEvents();
        $this->registerComponents();
        $this->registerResources();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the package authorization.
     */
    protected function registerAuthorization()
    {
        $this->app[Gate::class]->define('viewPulse', fn ($user = null) => $this->app->environment('local'));
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $this->app->booted(function () {
            $this->callAfterResolving('router', function (Router $router, Application $app) {
                $router->group([
                    'domain' => $app['config']->get('pulse.domain', null),
                    'prefix' => $app['config']->get('pulse.path'),
                    'middleware' => $app['config']->get('pulse.middleware', 'web'),
                ], function (Router $router) {
                    $router->get('/', function (Pulse $pulse, ViewFactory $view) {
                        return $view->make('pulse::dashboard');
                    });
                });
            });
        });
    }

    /**
     * Listen for the events that are relevant to the package.
     */
    protected function listenForEvents(): void
    {
        $this->app->booted(function () {
            $this->callAfterResolving(Dispatcher::class, function (Dispatcher $event, Application $app) {
                $event->listen(function (Logout $event) use ($app) {
                    $pulse = $app[Pulse::class];

                    $pulse->rescue(fn () => $pulse->rememberUser($event->user));
                });

                $event->listen([
                    Looping::class,
                    WorkerStopping::class,
                ], function ($event) use ($app) {
                    $app[Pulse::class]->store($app[Ingest::class]);
                });
            });

            $this->callAfterResolving(HttpKernel::class, function (HttpKernel $kernel, Application $app) {
                $kernel->whenRequestLifecycleIsLongerThan(-1, function () use ($app) { // @phpstan-ignore method.notFound
                    $app[Pulse::class]->store($app[Ingest::class]);
                });
            });

            $this->callAfterResolving(ConsoleKernel::class, function (ConsoleKernel $kernel, Application $app) {
                $kernel->whenCommandLifecycleIsLongerThan(-1, function () use ($app) { // @phpstan-ignore method.notFound
                    $app[Pulse::class]->store($app[Ingest::class]);
                });
            });
        });
    }

    /**
     * Register the package's components.
     */
    protected function registerComponents(): void
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade) {
            $blade->anonymousComponentPath(__DIR__.'/../resources/views/components', 'pulse');
        });

        $this->callAfterResolving('livewire', function (LivewireManager $livewire, Application $app) {
            $livewire->addPersistentMiddleware($app['config']->get('pulse.middleware', []));

            $livewire->component('pulse.cache', Livewire\Cache::class);
            $livewire->component('pulse.usage', Livewire\Usage::class);
            $livewire->component('pulse.queues', Livewire\Queues::class);
            $livewire->component('pulse.servers', Livewire\Servers::class);
            $livewire->component('pulse.slow-jobs', Livewire\SlowJobs::class);
            $livewire->component('pulse.exceptions', Livewire\Exceptions::class);
            $livewire->component('pulse.slow-routes', Livewire\SlowRoutes::class);
            $livewire->component('pulse.slow-queries', Livewire\SlowQueries::class);
            $livewire->component('pulse.period-selector', Livewire\PeriodSelector::class);
            $livewire->component('pulse.slow-outgoing-requests', Livewire\SlowOutgoingRequests::class);
        });
    }

    /**
     * Register the package's resources.
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
                Commands\WorkCommand::class,
                Commands\CheckCommand::class,
                Commands\RestartCommand::class,
                Commands\RegroupCommand::class,
                Commands\PurgeCommand::class,
            ]);
        }
    }
}

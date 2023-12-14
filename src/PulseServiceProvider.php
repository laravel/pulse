<?php

namespace Laravel\Pulse;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Factory as ViewFactory;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Ingests\RedisIngest;
use Laravel\Pulse\Ingests\StorageIngest;
use Laravel\Pulse\Storage\DatabaseStorage;
use Livewire\LivewireManager;
use RuntimeException;

/**
 * @internal
 */
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

        $this->app->singleton(Pulse::class);
        $this->app->bind(Storage::class, DatabaseStorage::class);

        $this->registerIngest();
    }

    /**
     * Register the ingest implementation.
     */
    protected function registerIngest(): void
    {
        $this->app->bind(Ingest::class, fn (Application $app) => match ($app->make('config')->get('pulse.ingest.driver')) {
            'storage' => $app->make(StorageIngest::class),
            'redis' => $app->make(RedisIngest::class),
            default => throw new RuntimeException("Unknown ingest driver [{$app->make('config')->get('pulse.ingest.driver')}]."),
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($enabled = $this->app->make('config')->get('pulse.enabled')) {
            $this->app->make(Pulse::class)->register($this->app->make('config')->get('pulse.recorders'));
            $this->listenForEvents();
        } else {
            $this->app->make(Pulse::class)->stopRecording();
        }

        $this->registerAuthorization();
        $this->registerRoutes();
        $this->registerComponents();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the package authorization.
     */
    protected function registerAuthorization(): void
    {
        $this->callAfterResolving(Gate::class, function (Gate $gate, Application $app) {
            $gate->define('viewPulse', fn ($user = null) => $app->environment('local'));
        });
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $this->callAfterResolving('router', function (Router $router, Application $app) {
            if ($app->make(Pulse::class)->registersRoutes()) {
                $router->group([
                    'domain' => $app->make('config')->get('pulse.domain', null),
                    'prefix' => $app->make('config')->get('pulse.path'),
                    'middleware' => $app->make('config')->get('pulse.middleware'),
                ], function (Router $router) {
                    $router->get('/', function (Pulse $pulse, ViewFactory $view) {
                        return $view->make('pulse::dashboard');
                    })->name('pulse');
                });
            }
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
                    $pulse = $app->make(Pulse::class);

                    $pulse->rescue(fn () => $pulse->rememberUser($event->user));
                });

                $event->listen([
                    Looping::class,
                    WorkerStopping::class,
                ], function () use ($app) {
                    $app->make(Pulse::class)->store();
                });
            });

            $this->callAfterResolving(HttpKernel::class, function (HttpKernel $kernel, Application $app) {
                $kernel->whenRequestLifecycleIsLongerThan(-1, function () use ($app) { // @phpstan-ignore method.notFound
                    $app->make(Pulse::class)->store();
                });
            });

            $this->callAfterResolving(ConsoleKernel::class, function (ConsoleKernel $kernel, Application $app) {
                $kernel->whenCommandLifecycleIsLongerThan(-1, function () use ($app) { // @phpstan-ignore method.notFound
                    $app->make(Pulse::class)->store();
                });
            });
        });

        $this->callAfterResolving(Dispatcher::class, function (Dispatcher $event, Application $app) {
            $event->listen([
                \Laravel\Octane\Events\RequestReceived::class, // @phpstan-ignore class.notFound
                \Laravel\Octane\Events\TaskReceived::class, // @phpstan-ignore class.notFound
                \Laravel\Octane\Events\TickReceived::class, // @phpstan-ignore class.notFound
            ], function ($event) {
                if ($event->sandbox->resolved(Pulse::class)) {
                    $event->sandbox->make(Pulse::class)->setContainer($event->sandbox);
                }
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
            $livewire->addPersistentMiddleware($app->make('config')->get('pulse.middleware', []));

            $livewire->component('pulse.cache', Livewire\Cache::class);
            $livewire->component('pulse.usage', Livewire\Usage::class);
            $livewire->component('pulse.queues', Livewire\Queues::class);
            $livewire->component('pulse.servers', Livewire\Servers::class);
            $livewire->component('pulse.slow-jobs', Livewire\SlowJobs::class);
            $livewire->component('pulse.exceptions', Livewire\Exceptions::class);
            $livewire->component('pulse.slow-requests', Livewire\SlowRequests::class);
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

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], ['pulse', 'pulse-migrations']);

            $this->publishes([
                __DIR__.'/../public' => public_path('vendor/pulse'),
            ], ['pulse', 'laravel-assets']);
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
                Commands\PurgeCommand::class,
            ]);
        }
    }
}

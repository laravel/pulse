<?php

namespace Laravel\Pulse;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Events\ExceptionReported;
use RuntimeException;
use Throwable;

/**
 * @internal
 *
 * @mixin \Laravel\Pulse\Contracts\Storage
 */
class Pulse
{
    use Concerns\ConfiguresAfterResolving, ForwardsCalls;

    /**
     * The list of metric recorders.
     *
     * @var \Illuminate\Support\Collection<int, object>
     */
    protected Collection $recorders;

    /**
     * The list of queued items.
     *
     * @var \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Value>
     */
    protected Collection $entries;

    /**
     * The list of queued lazy entry and value resolvers.
     *
     * @var \Illuminate\Support\Collection<int, callable>
     */
    protected Collection $lazy;

    /**
     * Indicates if Pulse should be recording.
     */
    protected bool $shouldRecord = true;

    /**
     * The entry filters.
     *
     * @var \Illuminate\Support\Collection<int, (callable(\Laravel\Pulse\Entry|\Laravel\Pulse\Value): bool)>
     */
    protected Collection $filters;

    /**
     * The users resolver.
     *
     * @var ?callable(\Illuminate\Support\Collection<int, string|int>): iterable<int, array{id: string|int, name: string, email?: ?string, avatar?: ?string, extra?: ?string}>
     */
    protected $usersResolver = null;

    /**
     * The authenticated user ID resolver.
     *
     * @var callable(): (int|string|null)
     */
    protected $authenticatedUserIdResolver = null;

    /**
     * The remembered user's ID.
     */
    protected int|string|null $rememberedUserId = null;

    /**
     * Indicates if Pulse routes will be registered.
     */
    protected bool $registersRoutes = true;

    /**
     * Handle exceptions using the given callback.
     *
     * @var ?callable(\Throwable): mixed
     */
    protected $handleExceptionsUsing = null;

    /**
     * The CSS paths to include on the dashboard.
     *
     * @var list<string|Htmlable>
     */
    protected $css = [__DIR__.'/../dist/pulse.css'];

    /**
     * Create a new Pulse instance.
     */
    public function __construct(protected Application $app)
    {
        $this->filters = collect([]);
        $this->recorders = collect([]);
        $this->entries = collect([]);
        $this->lazy = collect([]);
    }

    /**
     * Register a recorder.
     *
     * @param  array<class-string, array<mixed>|boolean>  $recorders
     */
    public function register(array $recorders): self
    {
        $recorders = collect($recorders)->map(function ($recorder, $key) {
            if ($recorder === false || (is_array($recorder) && ! ($recorder['enabled'] ?? true))) {
                return;
            }

            return $this->app->make($key);
        })->filter()->values();

        $this->afterResolving($this->app, 'events', fn (Dispatcher $event) => $recorders
            ->filter(fn ($recorder) => $recorder->listen ?? null)
            ->each(fn ($recorder) => $event->listen(
                $recorder->listen,
                fn ($event) => $this->rescue(fn () => Collection::wrap($recorder->record($event)))
            ))
        );

        $recorders
            ->filter(fn ($recorder) => method_exists($recorder, 'register'))
            ->each(function ($recorder) {
                $record = function (...$args) use ($recorder) {
                    $this->rescue(fn () => Collection::wrap($recorder->record(...$args)));
                };

                $this->app->call($recorder->register(...), ['record' => $record]);
            });

        $this->recorders = collect([...$this->recorders, ...$recorders]);

        return $this;
    }

    /**
     * Record an entry.
     */
    public function record(
        string $type,
        string $key,
        ?int $value = null,
        DateTimeInterface|int|null $timestamp = null,
    ): Entry {
        $timestamp ??= CarbonImmutable::now();

        $entry = new Entry(
            timestamp: $timestamp instanceof DateTimeInterface ? $timestamp->getTimestamp() : $timestamp,
            type: $type,
            key: $key,
            value: $value,
        );

        if ($this->shouldRecord) {
            $this->entries[] = $entry;
        }

        return $entry;
    }

    /**
     * Record a value.
     */
    public function set(
        string $type,
        string $key,
        string $value,
        DateTimeInterface|int|null $timestamp = null,
    ): Value {
        $timestamp ??= CarbonImmutable::now();

        $value = new Value(
            timestamp: $timestamp instanceof DateTimeInterface ? $timestamp->getTimestamp() : $timestamp,
            type: $type,
            key: $key,
            value: $value,
        );

        if ($this->shouldRecord) {
            $this->entries[] = $value;
        }

        return $value;
    }

    /**
     * Lazily capture items.
     */
    public function lazy(callable $closure): self
    {
        if ($this->shouldRecord) {
            $this->lazy[] = $closure;
        }

        return $this;
    }

    /**
     * Report the throwable exception to Pulse.
     */
    public function report(Throwable $e): self
    {
        $this->rescue(fn () => $this->app->make('events')->dispatch(new ExceptionReported($e)));

        return $this;
    }

    /**
     * Start recording.
     */
    public function startRecording(): self
    {
        $this->shouldRecord = true;

        return $this;
    }

    /**
     * Stop recording.
     */
    public function stopRecording(): self
    {
        $this->shouldRecord = false;

        return $this;
    }

    /**
     * Execute the given callback without recording.
     *
     * @template TReturn
     *
     * @param  (callable(): TReturn)  $callback
     * @return TReturn
     */
    public function ignore($callback): mixed
    {
        $cachedRecording = $this->shouldRecord;

        try {
            $this->shouldRecord = false;

            return $callback();
        } finally {
            $this->shouldRecord = $cachedRecording;
        }
    }

    /**
     * Flush the queue.
     */
    public function flush(): self
    {
        $this->entries = collect([]);
        $this->lazy = collect([]);

        return $this;
    }

    /**
     * Filter items before storage using the provided filter.
     *
     * @param  (callable(\Laravel\Pulse\Entry|\Laravel\Pulse\Value): bool)  $filter
     */
    public function filter(callable $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Store the queued items.
     */
    public function store(): int
    {
        $ingest = $this->app->make(Ingest::class);

        $this->lazy->each(fn ($lazy) => $lazy());

        $this->rescue(fn () => $ingest->ingest(
            $this->entries->filter($this->shouldRecord(...)),
        ));

        Lottery::odds(...$this->app->make('config')->get('pulse.ingest.trim_lottery'))
            ->winner(fn () => $this->rescue($ingest->trim(...)))
            ->choose();

        $this->rememberedUserId = null;

        return tap($this->entries->count(), $this->flush(...));
    }

    /**
     * Determine if the given entry should be recorded.
     */
    protected function shouldRecord(Entry|Value $entry): bool
    {
        return $this->filters->every(fn (callable $filter) => $filter($entry));
    }

    /**
     * Get the registered recorders.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function recorders(): Collection
    {
        return collect($this->recorders);
    }

    /**
     * Resolve the user details for the given user IDs.
     *
     * @param  \Illuminate\Support\Collection<int, string|int>  $ids
     * @return  \Illuminate\Support\Collection<int, array{id: string|int, name: string, email?: ?string, avatar?: ?string, extra?: ?string}>
     */
    public function resolveUsers(Collection $ids): Collection
    {
        if ($this->usersResolver) {
            return collect(($this->usersResolver)($ids));
        }

        if (class_exists($class = \App\Models\User::class) || class_exists($class = \App\User::class)) {
            return $class::whereKey($ids)->get()->map(fn ($user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        return $ids->map(fn (string|int $id) => [
            'id' => $id,
            'name' => "User ID: {$id}",
        ]);
    }

    /**
     * Resolve the user's details using the given closure.
     *
     * @param  (callable(\Illuminate\Support\Collection<int, string|int>): iterable<int, array{id: string|int, name: string, email?: ?string, avatar?: ?string, extra?: ?string}>)  $callback
     */
    public function users(callable $callback): self
    {
        $this->usersResolver = $callback;

        return $this;
    }

    /**
     * Get the authenticated user ID resolver.
     *
     * @return callable(): (int|string|null)
     */
    public function authenticatedUserIdResolver(): callable
    {
        if ($this->authenticatedUserIdResolver !== null) {
            return $this->authenticatedUserIdResolver;
        }

        $auth = $this->app->make('auth');

        if ($auth->hasUser()) {
            $id = $auth->id();

            return fn () => $id;
        }

        return fn () => $auth->id() ?? $this->rememberedUserId;
    }

    /**
     * Resolve the authenticated user id.
     */
    public function resolveAuthenticatedUserId(): string|int|null
    {
        return $this->authenticatedUserIdResolver()();
    }

    /**
     * Resolve the authenticated user ID with the given callback.
     */
    public function resolveAuthenticatedUserIdUsing(callable $callback): self
    {
        $this->authenticatedUserIdResolver = $callback;

        return $this;
    }

    /**
     * Set the user for the given callback.
     *
     * @template TReturn
     *
     * @param  (callable(): TReturn)  $callback
     * @return TReturn
     */
    public function withUser(Authenticatable|int|string|null $user, callable $callback): mixed
    {
        $cachedUserIdResolver = $this->authenticatedUserIdResolver;

        try {
            $id = $user instanceof Authenticatable
                ? $user->getAuthIdentifier()
                : $user;

            $this->authenticatedUserIdResolver = fn () => $id;

            return $callback();
        } finally {
            $this->authenticatedUserIdResolver = $cachedUserIdResolver;
        }
    }

    /**
     * Remember the authenticated user's ID.
     */
    public function rememberUser(Authenticatable $user): self
    {
        $this->rememberedUserId = $user->getAuthIdentifier();

        return $this;
    }

    /**
     * Register or return CSS for the Pulse dashboard.
     *
     * @param  string|Htmlable|list<string|Htmlable>|null  $css
     */
    public function css(string|Htmlable|array|null $css = null): string|self
    {
        if (func_num_args() === 1) {
            $this->css = array_values(array_unique(array_merge($this->css, Arr::wrap($css))));

            return $this;
        }

        return collect($this->css)->reduce(function ($carry, $css) {
            if ($css instanceof Htmlable) {
                return $carry.Str::finish($css->toHtml(), PHP_EOL);
            } else {
                if (($contents = @file_get_contents($css)) === false) {
                    throw new RuntimeException("Unable to load Pulse dashboard CSS path [$css].");
                }

                return $carry."<style>{$contents}</style>".PHP_EOL;
            }
        }, '');
    }

    /**
     * Return the compiled JavaScript from the vendor directory.
     */
    public function js(): string
    {
        if (($content = file_get_contents(__DIR__.'/../dist/pulse.js')) === false) {
            throw new RuntimeException('Unable to load the Pulse dashboard JavaScript.');
        }

        return "<script>{$content}</script>".PHP_EOL;
    }

    /**
     * Determine if Pulse may register routes.
     */
    public function registersRoutes(): bool
    {
        return $this->registersRoutes;
    }

    /**
     * Configure Pulse to not register its routes.
     */
    public function ignoreRoutes(): self
    {
        $this->registersRoutes = false;

        return $this;
    }

    /**
     * Handle exceptions using the given callback.
     *
     * @param  (callable(\Throwable): mixed)  $callback
     */
    public function handleExceptionsUsing(callable $callback): self
    {
        $this->handleExceptionsUsing = $callback;

        return $this;
    }

    /**
     * Execute the given callback handling any exceptions.
     *
     * @param  (callable(): mixed)  $callback
     */
    public function rescue(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            ($this->handleExceptionsUsing ?? fn () => null)($e);
        }
    }

    /**
     * Set the container instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->app = $container;

        return $this;
    }

    /**
     * Forward calls to the storage driver.
     *
     * @param  string  $method
     * @param  array<mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        $storage = $this->app->make(Storage::class);

        return $this->forwardCallTo($storage, $method, $parameters);
    }
}

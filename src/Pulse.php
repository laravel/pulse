<?php

namespace Laravel\Pulse;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Lottery;
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
     * Indicates if Pulse migrations will be run.
     */
    protected bool $runsMigrations = true;

    /**
     * Handle exceptions using the given callback.
     *
     * @var ?callable(\Throwable): mixed
     */
    protected $handleExceptionsUsing = null;

    /**
     * Create a new Pulse instance.
     */
    public function __construct(protected Application $app)
    {
        $this->filters = collect([]);
        $this->recorders = collect([]);
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
        int $value = 1,
        DateTimeInterface|int $timestamp = null,
    ): Entry {
        if ($timestamp === null) {
            $timestamp = CarbonImmutable::now();
        }

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
        mixed $value,
        DateTimeInterface|int $timestamp = null,
    ): Value {
        if ($timestamp === null) {
            $timestamp = CarbonImmutable::now();
        }

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
        } elseif (class_exists(\App\Models\User::class)) {
            return \App\Models\User::whereKey($ids)->get(['id', 'name', 'email']);
        } elseif (class_exists(\App\User::class)) {
            return \App\User::whereKey($ids)->get(['id', 'name', 'email']);
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
        // TODO review all this now we have `lazy`
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
     * Return the compiled CSS from the vendor directory.
     */
    public function css(): string
    {
        if (($content = file_get_contents(__DIR__.'/../dist/pulse.css')) === false) {
            throw new RuntimeException('Unable to load Pulse dashboard CSS.');
        }

        return $content;
    }

    /**
     * Return the compiled JavaScript from the vendor directory.
     */
    public function js(): string
    {
        if (($content = file_get_contents(__DIR__.'/../dist/pulse.js')) === false) {
            throw new RuntimeException('Unable to load the Pulse dashboard JavaScript.');
        }

        return $content;
    }

    /**
     * Determine if Pulse may run migrations.
     */
    public function runsMigrations(): bool
    {
        return $this->runsMigrations;
    }

    /**
     * Configure Pulse to not register its migrations.
     */
    public function ignoreMigrations(): self
    {
        $this->runsMigrations = false;

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

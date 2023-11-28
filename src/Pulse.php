<?php

namespace Laravel\Pulse;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Closure;
use DateTimeInterface;
use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Lottery;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Events\ExceptionReported;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
class Pulse
{
    use Concerns\ConfiguresAfterResolving;

    /**
     * The list of metric recorders.
     *
     * @var \Illuminate\Support\Collection<int, object>
     */
    protected Collection $recorders;

    /**
     * The list of queued entries and values.
     *
     * @var \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Value>
     */
    protected Collection $entries;

    /**
     * Indicates if Pulse should record entries.
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
    public function __construct(
        protected Application $app,
        protected AuthManager $auth,
        protected Repository $config,
    ) {
        $this->filters = collect([]);
        $this->recorders = collect([]);

        $this->flushEntries();
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
        Closure|string $key,
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
        Closure|string $key,
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
     * Retrieve values for the given type.
     *
     * @param  list<string>  $keys
     * @return \Illuminate\Support\Collection<
     *     int,
     *     array<
     *         string,
     *         array{
     *             timestamp: int,
     *             type: string,
     *             key: string,
     *             value: string
     *         }
     *     >
     * >
     */
    public function values(string $type, array $keys = null): Collection
    {
        return $this->app->make(Storage::class)->values($type, $keys);
    }

    /**
     * Retrieve aggregate values for plotting on a graph.
     *
     * @param  list<string>  $types
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, int|null>>>
     */
    public function graph(array $types, string $aggregate, CarbonInterval $interval): Collection
    {
        return $this->app->make(Storage::class)->graph($types, $aggregate, $interval);
    }

    /**
     * Retrieve aggregate values for the given type.
     *
     * @param  list<string>  $aggregates
     * @return \Illuminate\Support\Collection<int, object{
     *     key: string,
     *     max?: int,
     *     sum?: int,
     *     avg?: int,
     *     count?: int
     * }>
     */
    public function aggregate(
        string $type,
        array|string $aggregates,
        CarbonInterval $interval,
        string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        return $this->app->make(Storage::class)->aggregate($type, $aggregates, $interval, $orderBy, $direction, $limit);
    }

    /**
     * Retrieve aggregate values for the given types.
     *
     * @param  string|list<string>  $types
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function aggregateTypes(
        string|array $types,
        string $aggregate,
        CarbonInterval $interval,
        string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        return $this->app->make(Storage::class)->aggregateTypes($types, $aggregate, $interval, $orderBy, $direction, $limit);
    }

    /**
     * Retrieve an aggregate total for the given types.
     *
     * @param  string|list<string>  $types
     * @return \Illuminate\Support\Collection<string, int>
     */
    public function aggregateTotal(
        array|string $types,
        string $aggregate,
        CarbonInterval $interval,
    ) {
        return $this->app->make(Storage::class)->aggregateTotal($types, $aggregate, $interval);
    }

    /**
     * Report the throwable exception to Pulse.
     */
    public function report(Throwable $e): self
    {
        $this->rescue(fn () => $this->app['events']->dispatch(new ExceptionReported($e)));

        return $this;
    }

    /**
     * Start recording entries.
     */
    public function startRecording(): self
    {
        $this->shouldRecord = true;

        return $this;
    }

    /**
     * Stop recording entries.
     */
    public function stopRecording(): self
    {
        $this->shouldRecord = false;

        return $this;
    }

    /**
     * Execute the given callback without recording entries.
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
     * The pending entries to be recorded.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Value>
     */
    public function entries()
    {
        return $this->entries;
    }

    /**
     * Flush the queue.
     */
    public function flushEntries(): self
    {
        $this->entries = collect([]);

        return $this;
    }

    /**
     * Filter incoming entries using the provided filter.
     *
     * @param  (callable(\Laravel\Pulse\Entry|\Laravel\Pulse\Value): bool)  $filter
     */
    public function filter(callable $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Store the queued entries.
     */
    public function store(): self
    {
        $ingest = $this->app->make(Ingest::class);

        $this->rescue(fn () => $ingest->ingest(
            $this->entries->map(fn ($entry) => $entry->resolve())->filter($this->shouldRecord(...)),
        ));

        Lottery::odds(...$this->config->get('pulse.ingest.trim_lottery'))
            ->winner(fn () => $this->rescue($ingest->trim(...)))
            ->choose();

        $this->rememberedUserId = null;

        return $this->flushEntries();
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
    public function resolveUsersUsing(callable $callback): self
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

        if ($this->auth->hasUser()) {
            $id = $this->auth->id();

            return fn () => $id;
        }

        return fn () => $this->auth->id() ?? $this->rememberedUserId;
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
}

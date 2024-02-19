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
use Laravel\Pulse\Contracts\ResolvesUsers;
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
     * Indicates that Pulse is currently evaluating the buffer.
     */
    protected bool $evaluatingBuffer = false;

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
     * @param  array<class-string, array<mixed>|bool>  $recorders
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
                fn ($event) => $this->rescue(fn () => $recorder->record($event))
            ))
        );

        $recorders
            ->filter(fn ($recorder) => method_exists($recorder, 'register'))
            ->each(function ($recorder) {
                $this->app->call($recorder->register(...), [
                    'record' => fn (...$args) => $this->rescue(fn () => $recorder->record(...$args)),
                ]);
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

            $this->ingestWhenOverBufferSize();
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

            $this->ingestWhenOverBufferSize();
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

            $this->ingestWhenOverBufferSize();
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

        $this->rememberedUserId = null;

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
     * Ingest the entries.
     */
    public function ingest(): int
    {
        $this->resolveLazyEntries();

        return $this->ignore(function () {
            $entries = $this->rescue(fn () => $this->entries->filter($this->shouldRecord(...))) ?? collect([]);

            if ($entries->isEmpty()) {
                $this->flush();

                return 0;
            }

            $ingest = $this->app->make(Ingest::class);

            $count = $this->rescue(function () use ($entries, $ingest) {
                $ingest->ingest($entries);

                return $entries->count();
            }) ?? 0;

            // TODO remove fallback when tagging v1
            $odds = $this->app->make('config')->get('pulse.ingest.trim.lottery') ?? $this->app->make('config')->get('pulse.ingest.trim_lottery');

            Lottery::odds(...$odds)
                ->winner(fn () => $this->rescue($ingest->trim(...)))
                ->choose();

            $this->flush();

            return $count;
        });
    }

    /**
     * Digest the entries.
     */
    public function digest(): int
    {
        return $this->ignore(
            fn () => $this->app->make(Ingest::class)->digest($this->app->make(Storage::class))
        );
    }

    /**
     * Determine if Pulse wants to ingest entries.
     */
    public function wantsIngesting(): bool
    {
        return $this->lazy->isNotEmpty() || $this->entries->isNotEmpty();
    }

    /**
     * Start ingesting entires if over buffer size.
     */
    protected function ingestWhenOverBufferSize(): void
    {
        // To prevent recursion, we track when we are already evaluating the
        // buffer and resolving entries. When we are we may simply return
        // and the continue execution. We set the value to false later.
        if ($this->evaluatingBuffer) {
            return;
        }

        // TODO remove fallback when tagging v1
        $buffer = $this->app->make('config')->get('pulse.ingest.buffer') ?? 5_000;

        if (($this->entries->count() + $this->lazy->count()) > $buffer) {
            $this->evaluatingBuffer = true;

            $this->resolveLazyEntries();
        }

        if ($this->entries->count() > $buffer) {
            $this->evaluatingBuffer = true;

            $this->ingest();
        }

        $this->evaluatingBuffer = false;
    }

    /**
     * Resolve lazy entries.
     */
    protected function resolveLazyEntries(): void
    {
        $this->rescue(fn () => $this->lazy->each(fn ($lazy) => $lazy()));

        $this->lazy = collect([]);
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
     * @param  \Illuminate\Support\Collection<int, string>  $keys
     */
    public function resolveUsers(Collection $keys): ResolvesUsers
    {
        $resolver = $this->app->make(ResolvesUsers::class);

        return $resolver->load($keys);
    }

    /**
     * Resolve the users' details using the given closure.
     *
     * @deprecated
     *
     * @param  callable(\Illuminate\Support\Collection<int, mixed>): ?iterable<int|string, array{name: string, email?: ?string, avatar?: ?string, extra?: ?string}>  $callback
     */
    public function users(callable $callback): self
    {
        $this->app->instance(ResolvesUsers::class, new LegacyUsers($callback));

        return $this;
    }

    /**
     * Resolve the user's details using the given closure.
     *
     * @param  callable(\Illuminate\Contracts\Auth\Authenticatable): array{name: string, email?: ?string, avatar?: ?string, extra?: ?string}  $callback
     */
    public function user(callable $callback): self
    {
        $resolver = $this->app->make(ResolvesUsers::class);

        if (! method_exists($resolver, 'setFieldResolver')) {
            throw new RuntimeException('The configured user resolver does not support setting user fields');
        }

        $resolver->setFieldResolver($callback); // @phpstan-ignore method.nonObject

        return $this;
    }

    /**
     * Get the authenticated user ID resolver.
     *
     * @return callable(): (int|string|null)
     */
    public function authenticatedUserIdResolver(): callable
    {
        $auth = $this->app->make('auth');

        if ($auth->hasUser()) {
            $resolver = $this->app->make(ResolvesUsers::class);
            $key = $resolver->key($auth->user());

            return fn () => $key;
        }

        return function () {
            $auth = $this->app->make('auth');

            if ($auth->hasUser()) {
                $resolver = $this->app->make(ResolvesUsers::class);

                return $resolver->key($auth->user());
            } else {
                return $this->rememberedUserId;
            }
        };
    }

    /**
     * Resolve the authenticated user id.
     */
    public function resolveAuthenticatedUserId(): string|int|null
    {
        return $this->authenticatedUserIdResolver()();
    }

    /**
     * Remember the authenticated user's ID.
     */
    public function rememberUser(Authenticatable $user): self
    {
        $resolver = $this->app->make(ResolvesUsers::class);

        $this->rememberedUserId = $resolver->key($user);

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
            $this->css = array_values(array_unique(array_merge($this->css, Arr::wrap($css)), SORT_REGULAR));

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
        if (
            ($livewire = @file_get_contents(__DIR__.'/../../../livewire/livewire/dist/livewire.js')) === false &&
            ($livewire = @file_get_contents(__DIR__.'/../vendor/livewire/livewire/dist/livewire.js')) === false) {
            throw new RuntimeException('Unable to load the Livewire JavaScript.');
        }

        if (($pulse = @file_get_contents(__DIR__.'/../dist/pulse.js')) === false) {
            throw new RuntimeException('Unable to load the Pulse dashboard JavaScript.');
        }

        return "<script>{$livewire}</script>".PHP_EOL."<script>{$pulse}</script>".PHP_EOL;
    }

    /**
     * The default "vendor" cache keys that should be ignored by Pulse.
     *
     * @return list<string>
     */
    public static function defaultVendorCacheKeys(): array
    {
        return [
            '/(^laravel_vapor_job_attemp(t?)s:)/', // Laravel Vapor keys...
            '/^.+@.+\|(?:(?:\d+\.\d+\.\d+\.\d+)|[0-9a-fA-F:]+)(?::timer)?$/', // Breeze / Jetstream keys...
            '/^[a-zA-Z0-9]{40}$/', // Session IDs...
            '/^illuminate:/', // Laravel keys...
            '/^laravel:pulse:/', // Pulse keys...
            '/^nova/', // Nova keys...
            '/^telescope:/', // Telescope keys...
        ];
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
     * @template TReturn
     *
     * @param  (callable(): TReturn)  $callback
     * @return TReturn|null
     */
    public function rescue(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            ($this->handleExceptionsUsing ?? fn () => null)($e);
        }

        return null;
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
        return $this->ignore(fn () => $this->forwardCallTo($this->app->make(Storage::class), $method, $parameters));
    }
}

<?php

namespace Laravel\Pulse;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Lottery;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Update;
use RuntimeException;
use Throwable;

class Pulse
{
    use ListensForStorageOpportunities;

    /**
     * The list of queued entries or updates.
     *
     * @var \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>
     */
    protected Collection $queue;

    /**
     * Indicates if Pulse should record entries.
     */
    protected bool $shouldRecord = true;

    /**
     * The entry filters.
     *
     * @var \Illuminate\Support\Collection<int, (callable(\Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update): bool)>
     */
    protected Collection $filters;

    /**
     * Users resolver.
     *
     * @var ?(callable(\Illuminate\Support\Collection<int, string|int>): iterable<int, array{id: int|string, name: string, 'email'?: ?string}>)
     */
    protected $usersResolver;

    /**
     * The callback that should be used to authenticate Pulse users.
     *
     * @var ?(callable(\Illuminate\Http\Request): bool)
     */
    protected $authUsing = null;

    /**
     * Indicates if Pulse migrations will be run.
     */
    protected bool $runsMigrations = true;

    /**
     * Handle exceptions using the given callback.
     *
     * @var ?(callable(\Throwable): mixed)
     */
    protected $handleExceptionsUsing = null;

    /**
     * Create a new Pulse instance.
     */
    public function __construct(
        protected Repository $config,
        protected Ingest $ingest,
    ) {
        $this->filters = collect([]);

        $this->queue = collect([]);
    }

    /**
     * Stop recording entries.
     *
     * @template TReturn
     *
     * @param  (callable(): TReturn)  $callback
     */
    public function ignore($callback): mixed
    {
        $this->stopRecording();

        try {
            return $callback();
        } finally {
            $this->startRecording();
        }
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
     * Start recording entries.
     */
    public function startRecording(): self
    {
        $this->shouldRecord = true;

        return $this;
    }

    /**
     * Filter incoming entries using the provided filter.
     *
     * @param  (callable(\Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update): bool)  $filter
     */
    public function filter(callable $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Record the given entry.
     */
    public function record(Entry|Update $entry): self
    {
        if ($this->shouldRecord) {
            $this->queue[] = $entry;
        }

        return $this;
    }

    /**
     * Store the queued entries and flush the queue.
     */
    public function store(): self
    {
        if (! $this->shouldRecord) {
            $this->queue = collect([]);

            return $this;
        }

        $this->rescue(fn () => $this->ingest->ingest(
            $this->queue->map->resolve()->filter($this->shouldRun(...)),
        ));

        $this->rescue(fn () => Lottery::odds(...$this->config->get('pulse.ingest.lottery'))
            ->winner(fn () => $this->ingest->trim())
            ->choose());

        $this->queue = collect([]);

        return $this;
    }

    /**
     * The pending entries queue.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>
     */
    public function queue()
    {
        return $this->queue;
    }

    /**
     * Determine if the entry should be recorded.
     */
    protected function shouldRun(Entry|Update $entry): bool
    {
        return $this->filters->every(fn (callable $filter) => $filter($entry));
    }

    /**
     * Resolve the user's details using the given closure.
     *
     * @param  (callable(\Illuminate\Support\Collection<int, string|int>): iterable<int, array{id: int|string, name: string, 'email'?: ?string}>)  $callback
     */
    public function resolveUsersUsing(callable $callback): self
    {
        $this->usersResolver = $callback;

        return $this;
    }

    /**
     * Resolve the user's details using the given closure.
     *
     * @param  \Illuminate\Support\Collection<int, string|int>  $ids
     * @return  \Illuminate\Support\Collection<int, array{id: string|int, name: string, 'email'?: ?string}>
     */
    public function resolveUsers(Collection $ids): Collection
    {
        if ($this->usersResolver) {
            return collect(($this->usersResolver)($ids));
        }

        if (class_exists(\App\Models\User::class)) {
            return \App\Models\User::whereKey($ids)->get(['id', 'name', 'email']);
        }

        if (class_exists(\App\User::class)) {
            return \App\User::whereKey($ids)->get(['id', 'name', 'email']);
        }

        return $ids->map(fn (string|int $id) => [
            'id' => $id,
            'name' => "User ID: {$id}",
        ]);
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
     * Determine if the given request can access the Pulse dashboard.
     */
    public function authorize(Request $request): bool
    {
        // TODO
        return ($this->authUsing ?: fn () => App::environment('local'))($request);
    }

    /**
     * Set the callback that should be used to authorize Pulse users.
     *
     * @param  (callable(\Illuminate\Http\Request): bool)  $callback
     */
    public function auth(callable $callback): self
    {
        $this->authUsing = $callback;

        return $this;
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
     * Determine if Pulse may run migrations.
     */
    public function runsMigrations(): bool
    {
        return $this->runsMigrations;
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

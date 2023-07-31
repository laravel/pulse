<?php

namespace Laravel\Pulse;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Update;

class Pulse
{
    use ListensForStorageOpportunities;

    /**
     * The callback that should be used to authenticate Pulse users.
     *
     * @var \Closure
     */
    public static $authUsing;

    /**
     * Indicates if Pulse migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * The list of queued entries to be stored.
     */
    public array $entriesQueue = [];

    /**
     * The list of queued entry updates.
     */
    public array $updatesQueue = [];

    /**
     * Indicates if Pulse should record entries.
     */
    public bool $shouldRecord = true;

    /**
     * Users resolver.
     */
    public ?Closure $usersResolver = null;

    /**
     * The entry filters.
     */
    public Collection $filters;

    /**
     * Create a Pulse instance.
     */
    public function __construct()
    {
        $this->filters = new Collection([]);
    }

    /**
     * Stop recording entries.
     */
    public function shouldNotRecord(): self
    {
        $this->shouldRecord = false;

        return $this;
    }

    /**
     * Filter incoming entries using the provided filter.
     */
    public function filter(callable $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Resolve the user's details using the given closure.
     */
    public function resolveUsersUsing($callback): static
    {
        $this->usersResolver = $callback;

        return $this;
    }

    /**
     * Resolve the user's details using the given closure.
     */
    public function resolveUsers(Collection $ids): Collection
    {
        if ($this->usersResolver) {
            return collect(($this->usersResolver)($ids));
        }

        if (class_exists(\App\Models\User::class)) {
            return \App\Models\User::findMany($ids);
        }

        if (class_exists(\App\User::class)) {
            return \App\User::findMany($ids);
        }

        return $ids->map(fn ($id) => [
            'id' => $id,
        ]);
    }

    /**
     * Record the given entry.
     */
    public function record(Entry $entry): void
    {
        if ($this->shouldRecord($entry)) {
            $this->entriesQueue[$entry->table][] = $entry->attributes;
        }
    }

    /**
     * Record the given entry update.
     */
    public function recordUpdate(Update $update): void
    {
        if ($this->shouldRecord($update)) {
            $this->updatesQueue[] = $update;
        }
    }

    /**
     * Store the queued entries and flush the queue.
     */
    public function store(): void
    {
        foreach ($this->entriesQueue as $table => $rows) {
            DB::table($table)->insert($rows);
        }

        foreach ($this->updatesQueue as $update) {
            $update->perform();
        }

        $this->entriesQueue = [];
        $this->updatesQueue = [];
    }

    /**
     * Return the compiled CSS from the vendor directory.
     */
    public function css(): string
    {
        return file_get_contents(__DIR__.'/../dist/pulse.css');
    }

    /**
     * Return the compiled JavaScript from the vendor directory.
     */
    public function js(): string
    {
        return file_get_contents(__DIR__.'/../dist/pulse.js');
    }

    /**
     * Determine if the given request can access the Pulse dashboard.
     */
    public function check(Request $request): bool
    {
        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authorize Pulse users.
     */
    public static function auth(Closure $callback): static
    {
        static::$authUsing = $callback;

        return new self;
    }

    /**
     * Configure Pulse to not register its migrations.
     */
    public static function ignoreMigrations(): static
    {
        static::$runsMigrations = false;

        return new self;
    }

    /**
     * Determine if the entry should be recorded.
     */
    protected function shouldRecord(Entry|Update $entry): bool
    {
        return $this->shouldRecord && $this->filters->every(fn (callable $filter) => $filter($entry));
    }
}

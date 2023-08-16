<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsExceptions;
use Laravel\Pulse\Contracts\SupportsServers;
use Laravel\Pulse\Contracts\SupportsSlowJobs;
use Laravel\Pulse\Contracts\SupportsSlowOutgoingRequests;
use Laravel\Pulse\Contracts\SupportsSlowQueries;
use Laravel\Pulse\Contracts\SupportsSlowRoutes;
use Laravel\Pulse\Contracts\SupportsUsage;
use Laravel\Pulse\Entries\Table;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Queries\MySql;

class Database implements Storage,
    SupportsUsage,
    SupportsServers,
    SupportsSlowJobs,
    SupportsExceptions,
    SupportsSlowRoutes,
    SupportsSlowQueries,
    SupportsSlowOutgoingRequests
{
    /**
     * Create a new Database Storage instance.
     *
     * @param  array{connection: string, retain: \DateInterval}  $config
     */
    public function __construct(protected array $config, protected DatabaseManager $manager)
    {
        //
    }

    /**
     * Store the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>  $entries
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Update>  $updates
     */
    public function store(Collection $entries, Collection $updates): void
    {
        if ($entries->isEmpty() && $updates->isEmpty()) {
            return;
        }

        $this->connection()->transaction(function () use ($entries, $updates) {
            $entries->groupBy('table.value')
                ->each(fn ($rows, $table) => $rows->chunk(1000)
                    ->map(fn ($inserts) => $inserts->pluck('attributes')->all())
                    ->each($this->connection()->table($table)->insert(...)));

            $updates->each(fn ($update) => $update->perform($this->connection()));
        });
    }

    /**
     * Trim the stored entries.
     */
    public function trim(): void
    {
        Table::all()
            ->each(fn ($table) => $this->connection()
                ->table($table->value)
                ->where('date', '<', (new CarbonImmutable)->subSeconds((int) $this->trimAfter()->totalSeconds)->toDateTimeString())
                ->delete());
    }

    public function usage(Interval $interval, string $type): Collection
    {
        $query = match ($this->config['driver']) {
            'mysql' => new MySql\Usage(),
            'postgres' => new Postgres\Usage(),
        };

        return $query(
            $this->connection(), $interval, $type,
            Pulse::resolveUsers(...), $this->config['pulse']['slow_endpoint_threshold']
        );
    }

    /**
     * Retrieve the servers.
     */
    public function servers(Interval $interval): Collection
    {
        $query = match ($this->config['driver']) {
            'mysql' => new MySql\Servers(),
            'postgres' => null, // TODO,
        };

        return $query($this->connection(), $interval, $this->config['pulse']['graph_aggregation']);
    }

    /**
     * Retrieve the slow jobs.
     *
     * @return \Illuminate\Support\Collection<int, array{job: string, count: int, slowest: int}>
     */
    public function slowJobs(Interval $interval): Collection
    {
        $query = match ($this->config['driver']) {
            'mysql' => new MySql\SlowJobs(),
            'postgres' => null, // TODO,
        };

        return $query($this->connection(), $interval, $this->config['pulse']['slow_job_threshold']);
    }

    /**
     * Retrieve the exceptions.
     *
     * @param  'last_occurrence'|'count'  $orderBy
     * @return \Illuminate\Support\Collection<int, array{class: string, location: string, count: int, last_occurrence: string}>
     */
    public function exceptions(Interval $interval, string $orderBy): Collection
    {
        $query = match ($this->config['driver']) {
            'mysql' => new MySql\Exceptions(),
            'postgres' => null, // TODO,
        };

        return $query($this->connection(), $interval, $orderBy);
    }

    public function slowRoutes(Interval $interval): Collection
    {
        $query = match ($this->config['driver']) {
            'mysql' => new MySql\SlowRoutes(),
            'postgres' => null, // TODO,
        };

        return $query(
            $this->connection(), $interval,
            fn ($method, $path) => Route::getRoutes()->get($method)[$path] ?? null,
            $this->config['pulse']['slow_endpoint_threshold']
        );
    }

    public function slowQueries(Interval $interval): Collection
    {
        $query = match ($this->config['driver']) {
            'mysql' => new MySql\SlowQueries(),
            'postgres' => null, // TODO,
        };

        return $query($this->connection(), $interval, $this->config['pulse']['slow_query_threshold']);
    }

    /**
     * Retrieve the slow outgoing requests.
     *
     * @return \Illuminate\Support\Collection<int, array{uri: string, count: int, slowest: int}>
     */
    public function slowOutgoingRequests(Interval $interval): Collection
    {
        $query = match ($this->config['driver']) {
            'mysql' => new MySql\SlowQueries(),
            'postgres' => null, // TODO,
        };

        return $query($this->connection(), $interval, $this->config['pulse']['slow_query_threshold']);
    }

    /**
     * The interval to trim the storage to.
     */
    protected function trimAfter(): Interval
    {
        return new Interval($this->config['retain'] ?? 'P7D');
    }

    /**
     * Get the database connection.
     */
    protected function connection(): Connection
    {
        return $this->manager->connection($this->config['connection']);
    }
}

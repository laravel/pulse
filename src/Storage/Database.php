<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Update;

class Database implements Storage
{
    /**
     * Create a new Database storage instance.
     */
    public function __construct(
        protected DatabaseManager $manager,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Store the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>  $items
     */
    public function store(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        [$inserts, $updates] = $items->partition(fn (Entry|Update $entry) => $entry instanceof Entry);

        $this->connection()->transaction(function () use ($inserts, $updates) {
            $inserts->groupBy('table')
                ->each(fn (Collection $rows, string $table) => $rows->chunk(1000)
                    ->map(fn (Collection $inserts) => $inserts->pluck('attributes')->all())
                    ->each($this->connection()->table($table)->insert(...)));

            $updates->each(fn (Update $update) => $update->perform($this->connection()));
        });
    }

    /**
     * Trim the stored entries.
     */
    public function trim(): void
    {
        $this->tables()
            ->each(fn (string $table) => $this->connection()
                ->table($table)
                ->where('date', '<', (new CarbonImmutable)->subSeconds((int) $this->trimAfter()->totalSeconds)->toDateTimeString())
                ->delete());
    }

    /**
     *  Pulse's database tables.
     *
     *  @return \Illuminate\Support\Collection<int, string>
     */
    protected function tables(): Collection
    {
        return collect([
            'pulse_cache_hits',
            'pulse_exceptions',
            'pulse_jobs',
            'pulse_outgoing_requests',
            'pulse_queries',
            'pulse_queue_sizes',
            'pulse_requests',
            'pulse_servers',
            ...($this->config->get('pulse.storage.additional_tables') ?? []),
        ]);
    }

    /**
     * The interval to trim the storage to.
     */
    protected function trimAfter(): Interval
    {
        return new Interval($this->config->get('pulse.retain') ?? 'P7D');
    }

    /**
     * Get the database connection.
     */
    protected function connection(): Connection
    {
        return $this->manager->connection(
            $this->config->get('pulse.storage.database.connection') ?? $this->config->get('database.default')
        );
    }
}

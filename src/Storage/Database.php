<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;

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
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>  $entries
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Update>  $updates
     */
    public function store(Collection $entries, Collection $updates): void
    {
        if ($entries->isEmpty() && $updates->isEmpty()) {
            return;
        }

        $this->connection()->transaction(function () use ($entries, $updates) {
            $entries->groupBy('table')
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
        $this->tables()
            ->each(fn ($table) => $this->connection()
                ->table($table)
                ->where('date', '<', (new CarbonImmutable)->subSeconds((int) $this->trimAfter()->totalSeconds)->toDateTimeString())
                ->delete());
    }

    /**
     *  Pulse's database tables.
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
        return $this->manager->connection($this->config->get(
            "pulse.storage.{$this->config->get('pulse.storage.driver')}.connection"
        ));
    }
}

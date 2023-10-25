<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Update;

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
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Update>  $items
     */
    public function store(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        [$inserts, $updates] = $items->partition(fn (Entry|Update $entry) => $entry instanceof Entry);

        $this->connection()->transaction(function () use ($inserts, $updates) {
            $inserts->groupBy('table')
                ->each(fn (Collection $rows, string $table) => $rows->chunk($this->config->get('pulse.storage.database.chunk'))
                    ->map(fn (Collection $inserts) => $inserts->pluck('attributes')->all())
                    ->each($this->connection()->table($table)->insert(...)));

            $updates->each(function (Update $update) {
                if (is_array($update->attributes)) {
                    $this->connection()
                        ->table($update->table)
                        ->where($update->conditions)
                        ->update($update->attributes);
                } else {
                    $existing = $this->connection()
                        ->table($update->table)
                        ->where($update->conditions)
                        ->first();

                    if ($existing === null) {
                        return;
                    }

                    $this->connection()
                        ->table($update->table)
                        ->where($update->conditions)
                        ->update(($update->attributes)((array) $existing));
                }
            });
        });
    }

    /**
     * Trim the stored entries.
     */
    public function trim(Collection $tables): void
    {
        $tables->each(fn (string $table) => $this->connection()
            ->table($table)
            ->where('date', '<', (new CarbonImmutable)->subSeconds((int) $this->trimAfter()->totalSeconds)->toDateTimeString())
            ->delete());
    }

    /**
     * The interval to trim the storage to.
     */
    protected function trimAfter(): Interval
    {
        return str_starts_with($value = $this->config->get('pulse.retain'), 'P')
            ? new Interval($value)
            : Interval::createFromDateString($value);
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

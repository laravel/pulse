<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Type;

class Database implements Storage
{
    /**
     * Create a new Database Storage instance.
     *
     * @param  array<string, mixed>  $config
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
            $entries->groupBy('table')->each(fn ($rows, $table) => $rows->chunk(1000)
                ->map(fn ($inserts) => $inserts->pluck('attributes')->all())
                ->each($this->connection()->table($table)->insert(...)));

            $updates->each(fn ($update) => $update->perform($this->connection()));
        });
    }

    /**
     * Retain the ingested entries only for the given interval.
     */
    public function retain(Interval $interval): void
    {
        Type::all()->each(fn (Type $type) => $this->connection()->table($type->value)
            ->where('date', '<', (new CarbonImmutable)->subSeconds($interval->seconds)->toDateTimeString())
            ->delete());
    }

    /**
     * Get the database connection.
     */
    protected function connection(): Connection
    {
        return $this->manager->connection($this->config['connection']);
    }
}

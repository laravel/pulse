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
    public function __construct(protected array $config, protected DatabaseManager $manager)
    {
        //
    }

    /**
     * Store the entries and updates.
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

            $updates->each(fn ($update) => $update->perform($db));
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

    protected function connection(): Connection
    {
        return $this->manager->connection($this->config['connection']);
    }
}

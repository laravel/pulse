<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Type;

class Database implements Storage
{
    public function __construct(protected array $config, protected Connection $db)
    {
        //
    }

    /**
     * Store the entries and updates.
     */
    public function store(array $entries, array $updates): void
    {
        if ($entries === [] && $updates === []) {
            return;
        }

        $this->db->transaction(function () use ($entries, $updates) {
            collect($entries)->each(fn ($rows, $table) => collect($rows)
                ->chunk(1000)
                ->map->all()
                ->each($this->db->table($table)->insert(...)));

            collect($updates)->each(fn ($update) => $update->perform($db));
        });
    }

    /**
     * Trim the ingest.
     */
    public function trim(CarbonImmutable $oldest): void
    {
        Type::all()->each(fn (Type $type) => $this->db->table($type->value)
            ->where('date', '<', $oldest->toDateTimeString())
            ->delete());
    }
}

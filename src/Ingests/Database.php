<?php

namespace Laravel\Pulse\Ingests;

use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Ingest;

class Database implements Ingest
{
    /**
     * Ingest the entries and updates without throwing exceptions.
     */
    public function ingestSilently(array $entries, array $updates): void
    {
        rescue(fn () => $this->ingest($entries, $updates), report: false);
    }

    /**
     * Ingest the entries and updates.
     */
    public function ingest(array $entries, array $updates): void
    {
        DB::transaction(function () use ($entries, $updates) {
            collect($entries)->each(fn ($rows, $table) => collect($rows)
                ->chunk(1000)
                ->map->all()
                ->each(DB::table($table)->insert(...)));

            collect($updates)->each(fn ($update) => $update->perform());
        });
    }
}

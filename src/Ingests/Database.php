<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Entries\Type;

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
        if ($entries === [] && $updates === []) {
            return;
        }

        DB::transaction(function () use ($entries, $updates) {
            collect($entries)->each(fn ($rows, $table) => collect($rows)
                ->chunk(1000)
                ->map->all()
                ->each(DB::table($table)->insert(...)));

            collect($updates)->each(fn ($update) => $update->perform());
        });
    }

    /**
     * Trim the ingest without throwing exceptions.
     */
    public function trimSilently(CarbonImmutable $oldest): void
    {
        rescue(fn () => $this->trim($oldest), report: false);
    }

    /**
     * Trim the ingest.
     */
    public function trim(CarbonImmutable $oldest): void
    {
        Type::all()->each(fn (Type $type) => DB::table($type->value)
            ->where('date', '<', $oldest->toDateTimeString())
            ->delete());
    }
}

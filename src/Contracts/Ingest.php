<?php

namespace Laravel\Pulse\Contracts;

use Illuminate\Support\Collection;

interface Ingest
{
    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>  $entries
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Update>  $updates
     */
    public function ingest(Collection $entries, Collection $updates): void;

    /**
     * Trim the ingested entries.
     */
    public function trim(): void;

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage, int $count): int;
}

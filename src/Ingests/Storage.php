<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage as StorageContract;
use RuntimeException;

class Storage implements Ingest
{
    /**
     * Create a new Storage Ingest instance.
     */
    public function __construct(protected StorageContract $storage)
    {
        //
    }

    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>  $entries
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Update>  $updates
     */
    public function ingest(Collection $entries, Collection $updates): void
    {
        $this->storage->store($entries, $updates);
    }

    /**
     * Trim the ingested entries.
     */
    public function trim(): void
    {
        $this->storage->trim();
    }

    /**
     * Store the ingested entries.
     */
    public function store(StorageContract $store, int $count): int
    {
        throw new RuntimeException('The storage ingest driver does not need to process entries.');
    }
}

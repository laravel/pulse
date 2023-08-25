<?php

namespace Laravel\Pulse\Ingests;

use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage as StorageContract;
use Laravel\Pulse\Pulse;
use RuntimeException;

class Storage implements Ingest
{
    /**
     * Create a new Storage Ingest instance.
     */
    public function __construct(protected StorageContract $storage, protected Pulse $pulse)
    {
        //
    }

    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>  $items
     */
    public function ingest(Collection $items): void
    {
        $this->storage->store($items);
    }

    /**
     * Trim the ingested entries.
     */
    public function trim(): void
    {
        $this->storage->trim($this->pulse->tables());
    }

    /**
     * Store the ingested entries.
     */
    public function store(StorageContract $store, int $count): int
    {
        throw new RuntimeException('The storage ingest driver does not need to process entries.');
    }
}

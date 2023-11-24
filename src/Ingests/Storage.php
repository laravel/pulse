<?php

namespace Laravel\Pulse\Ingests;

use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage as StorageContract;

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
     * Ingest the items.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $items
     */
    public function ingest(Collection $items): void
    {
        $this->storage->store($items);
    }

    /**
     * Trim the ingest.
     */
    public function trim(): void
    {
        $this->storage->trim();
    }

    /**
     * Store the ingested entries.
     */
    public function store(StorageContract $store): int
    {
        return 0;
    }
}

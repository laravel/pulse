<?php

namespace Laravel\Pulse\Ingests;

use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;

class StorageIngest implements Ingest
{
    /**
     * Create a new Storage Ingest instance.
     */
    public function __construct(protected Storage $storage)
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
     * Store the ingested items.
     */
    public function store(Storage $storage): int
    {
        return 0;
    }
}

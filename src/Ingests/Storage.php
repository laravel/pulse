<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage as StorageContract;
use RuntimeException;

class Storage implements Ingest
{
    public function __construct(protected StorageContract $storage)
    {
        //
    }

    /**
     * Ingest the entries and updates.
     */
    public function ingest(array $entries, array $updates): void
    {
        $this->storage->store($entries, $updates);
    }

    /**
     * Trim the ingested entries.
     */
    public function trim(CarbonImmutable $oldest): void
    {
        $this->storage->trim($oldest);
    }

    /**
     * Store the ingested entries.
     */
    public function store(Storage $store, int $count): int
    {
        throw new RuntimeException('The storage ingest driver does not need to process entries.');
    }
}

<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;
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
    public function ingest(Collection $entries, Collection $updates): void
    {
        $this->storage->store($entries, $updates);
    }

    /**
     * Retain the ingested entries only for the given interval.
     */
    public function retain(Interval $interval): void
    {
        $this->storage->retain($interval);
    }

    /**
     * Store the ingested entries.
     */
    public function store(StorageContract $store, int $count): int
    {
        throw new RuntimeException('The storage ingest driver does not need to process entries.');
    }
}

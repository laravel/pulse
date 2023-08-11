<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonImmutable;

interface Ingest
{
    /**
     * Ingest the entries and updates.
     */
    public function ingest(array $entries, array $updates): void;

    /**
     * Trim the ingested entries.
     */
    public function trim(CarbonImmutable $oldest): void;

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage, int $count): int;
}

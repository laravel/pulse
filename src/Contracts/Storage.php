<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonImmutable;

interface Storage
{
    /**
     * Store the entries and updates.
     */
    public function store(array $entries, array $updates): void;

    /**
     * Trim the ingest.
     */
    public function trim(CarbonImmutable $oldest): void;
}

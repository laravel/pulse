<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonImmutable;

interface Ingest
{
    /**
     * Ingest the entries and updates without throwing exceptions.
     */
    public function ingestSilently(array $entries, array $updates): void;

    /**
     * Trim the ingest without throwing exceptions.
     */
    public function trimSilently(CarbonImmutable $oldest): void;
}

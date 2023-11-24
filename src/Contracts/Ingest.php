<?php

namespace Laravel\Pulse\Contracts;

use Illuminate\Support\Collection;

interface Ingest
{
    /**
     * Ingest the items.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $items
     */
    public function ingest(Collection $items): void;

    /**
     * Trim the ingest.
     */
    public function trim(): void;

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage): int;
}

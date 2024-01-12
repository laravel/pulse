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
     * Digest the ingested items.
     */
    public function digest(Storage $storage): int;

    /**
     * Trim the ingest.
     */
    public function trim(): void;
}

<?php

namespace Laravel\Pulse\Contracts;

use Illuminate\Support\Collection;
use Laravel\Pulse\Entry;

interface Ingest
{
    /**
     * Ingest the items.
     *
     * @param  Collection<int, Entry>  $items
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

<?php

namespace Laravel\Pulse\Ingests;

use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;

class NullIngest implements Ingest
{
    /**
     * Ingest the items.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $items
     */
    public function ingest(Collection $items): void
    {
        //
    }

    /**
     * Digest the ingested items.
     */
    public function digest(Storage $storage): int
    {
        return 0;
    }

    /**
     * Trim the ingest.
     */
    public function trim(): void
    {
        //
    }
}

<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Database\Connection;

abstract class Update
{
    /**
     * The update's table.
     */
    abstract public function table(): string;

    /**
     * Resolve the update for ingest and storage.
     */
    public function resolve(): self
    {
        return $this;
    }
}

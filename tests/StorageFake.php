<?php

namespace Tests;

use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;

class StorageFake implements Storage
{
    /**
     * Create a new fake instance.
     */
    public function __construct(public Collection $stored = new Collection)
    {
        //
    }

    /**
     * Store the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $items
     */
    public function store(Collection $items): void
    {
        $this->stored = $this->stored->merge($items);
    }

    /**
     * Trim the storage.
     */
    public function trim(): void
    {
        //
    }

    /**
     * Purge the stored entries from the given tables.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables
     */
    public function purge(Collection $tables): void
    {
        //
    }
}

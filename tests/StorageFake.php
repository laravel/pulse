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
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Update>  $items
     */
    public function store(Collection $items): void
    {
        $this->stored = $this->stored->merge($items);
    }

    /**
     * Trim the stored entries from the given tables.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables
     */
    public function trim(Collection $tables): void
    {
        //
    }
}

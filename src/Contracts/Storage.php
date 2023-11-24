<?php

namespace Laravel\Pulse\Contracts;

use Illuminate\Support\Collection;

interface Storage
{
    /**
     * Store the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $items
     */
    public function store(Collection $items): void;

    /**
     * Trim the storage.
     */
    public function trim(): void;

    /**
     * Purge the stored entries from the given tables.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables
     */
    public function purge(Collection $tables): void;
}

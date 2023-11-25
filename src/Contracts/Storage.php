<?php

namespace Laravel\Pulse\Contracts;

use Illuminate\Support\Collection;

interface Storage
{
    /**
     * Store the items.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Value>  $items
     */
    public function store(Collection $items): void;

    /**
     * Trim the storage.
     */
    public function trim(): void;

    /**
     * Purge the storage.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables
     */
    public function purge(Collection $tables): void;
}

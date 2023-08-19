<?php

namespace Laravel\Pulse\Contracts;

use Illuminate\Support\Collection;

interface Storage
{
    /**
     * Store the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>  $items
     */
    public function store(Collection $items): void;

    /**
     * Trim the stored entries.
     */
    public function trim(): void;
}

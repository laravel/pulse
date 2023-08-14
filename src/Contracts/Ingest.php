<?php

namespace Laravel\Pulse\Contracts;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;

interface Ingest
{
    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>  $entries
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Update>  $updates
     */
    public function ingest(Collection $entries, Collection $updates): void;

    /**
     * Retain the ingested entries only for the given interval.
     */
    public function retain(Interval $interval): void;

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage, int $count): int;
}

<?php

namespace Laravel\Pulse\Entries;

class Entry
{
    public function __construct(public string $table, public array $attributes)
    {
        //
    }

    /**
     * The entries table.
     */
    public function table(): string
    {
        return $this->table;
    }
}

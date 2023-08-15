<?php

namespace Laravel\Pulse\Entries;

class Entry
{
    /**
     * Create a new Entry instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public Table $table, public array $attributes)
    {
        //
    }

    /**
     * The entries table.
     */
    public function table(): Table
    {
        return $this->table;
    }
}

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

    /**
     * Resolve the entry's attributes.
     */
    public function resolve(): self
    {
        return new self($this->table, array_map(value(...), $this->attributes));
    }
}

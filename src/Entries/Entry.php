<?php

namespace Laravel\Pulse\Entries;

class Entry
{
    /**
     * Create a new Entry instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public string $table, public array $attributes)
    {
        //
    }

    /**
     * Resolve the entry for ingest and storage.
     */
    public function resolve(): self
    {
        return new self($this->table, array_map(value(...), $this->attributes));
    }
}

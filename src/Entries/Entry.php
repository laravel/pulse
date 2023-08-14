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
     * The entries table.
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Determine if the update is the given type.
     */
    public function is(Type $type): bool
    {
        return $this->type() === $type;
    }

    /**
     * The type of update.
     */
    public function type(): Type
    {
        return Type::from($this->table());
    }
}

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

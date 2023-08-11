<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Database\Connection;

abstract class Update
{
    /**
     * The update's table.
     */
    abstract public function table(): string;

    /**
     * Perform the update.
     */
    abstract public function perform(Connection $db): void;

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

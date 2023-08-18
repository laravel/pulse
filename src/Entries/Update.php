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
}

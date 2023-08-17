<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;

abstract class Update
{
    /**
     * The update's table.
     */
    abstract public function table(): Table;

    /**
     * Perform the update.
     */
    abstract public function perform(Connection $db): void;
}

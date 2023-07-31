<?php

namespace Laravel\Pulse\Entries;

abstract class Update
{
    /**
     * The update's table.
     */
    abstract public function table(): string;

    /**
     * Perform the update.
     */
    abstract public function perform(): void;
}

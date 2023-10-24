<?php

namespace Laravel\Pulse\Exceptions;

use RuntimeException;

class RedisVersionException extends RuntimeException
{
    public function __construct(public string $command, public string $minimum, public string $actual)
    {
        parent::__construct("Pulse requires Redis [{$minimum}] or higher. Found [{$actual}].");
    }
}

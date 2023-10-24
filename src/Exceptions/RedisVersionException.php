<?php

namespace Laravel\Pulse\Exceptions;

use RuntimeException;

class RedisVersionException extends RuntimeException
{
    /**
     * @var list<string>
     */
    public array $commands;

    /**
     * @param  string|list<string>  $commands
     */
    public function __construct(string|array $commands, public string $minimum, public string $actual)
    {
        $this->commands = (array) $commands;

        parent::__construct("Pulse requires Redis [{$minimum}] or higher. Found [{$actual}] while running ".collect($this->commands)->map(fn ($command) => "[$command]")->implode(',').'.');
    }
}

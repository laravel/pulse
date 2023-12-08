<?php

namespace Laravel\Pulse\Support;

use RuntimeException;
use Throwable;

/**
 * @internal
 */
class RedisServerException extends RuntimeException
{
    /**
     * Create an exception from the client's error message.
     */
    public static function whileRunningCommand(string $command, string $message, ?Throwable $previous = null): self
    {
        if (str_starts_with($message, 'ERR syntax error')) {
            $message = "The Redis version does not support the command or some of its arguments [{$command}]. Redis error: [{$message}].";
        } else {
            $message = "Error running command [{$command}]. Redis error: [{$message}].";
        }

        return new self($message, previous: $previous);
    }
}

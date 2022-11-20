<?php

namespace Laravel\Pulse\Handlers;

use Throwable;

class HandleException
{
    /**
     * Handle an exception.
     */
    public function __invoke(Throwable $e): void
    {
        ray('Received Exception...');
    }
}

<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Log\Events\MessageLogged;

class HandleLogMessage
{
    /**
     * Handle a log message.
     */
    public function __invoke(MessageLogged $event): void
    {
        ray('Message Logged: '.$event->message);
    }
}

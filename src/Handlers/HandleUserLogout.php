<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Auth\Events\Logout;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class HandleUserLogout
{
    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Handle the user logging out.
     */
    public function __invoke(Logout $event): void
    {
        $this->pulse->rescue(fn () => $this->pulse->rememberUser($event->user));
    }
}

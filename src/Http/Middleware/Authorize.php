<?php

namespace Laravel\Pulse\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected Gate $gate,
    ) {
        //
    }

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $this->gate->authorize('viewPulse');

        return $next($request);
    }
}

<?php

namespace Laravel\Pulse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pulse\Pulse;

class Authorize
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(protected Pulse $pulse)
    {
        //
    }

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        return $this->pulse->authorize($request) ? $next($request) : abort(403);
    }
}

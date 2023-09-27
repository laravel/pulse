<?php

namespace Laravel\Pulse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

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
        $response = $this->pulse->authorize($request);

        if ($response instanceof Response) {
            return $response;
        }

        return $response ? $next($request) : abort(403);
    }
}

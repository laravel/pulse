<?php

namespace Laravel\Pulse\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pulse\Facades\Pulse;

class Authorize
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        return Pulse::check($request) ? $next($request) : abort(403);
    }
}

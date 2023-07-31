<?php

namespace Laravel\Pulse\Http\Middleware;

use Laravel\Pulse\Facades\Pulse;

class Authorize
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response|null
     */
    public function handle($request, $next)
    {
        return Pulse::check($request) ? $next($request) : abort(403);
    }
}

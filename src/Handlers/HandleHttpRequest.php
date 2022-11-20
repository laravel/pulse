<?php

namespace Laravel\Pulse\Handlers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\RouteAction;

class HandleHttpRequest
{
    /**
     * Handle the completion of an HTTP request.
     */
    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        ray('Request Duration: '.$startedAt->diffInMilliseconds(now()).'ms');

        $action = $request->route()?->getAction();
        $hasController = $action && is_string($action['uses']) && ! RouteAction::containsSerializedClosure($action);

        ray('Route Path: '.$request->route()?->uri());

        if ($hasController) {
            $parsedAction = Str::parseCallback($action['uses']);
            ray('Route Controller: '.$parsedAction[0].'@'.$parsedAction[1]);
        }
    }
}

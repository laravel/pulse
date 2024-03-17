<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

trait LivewireRoutes
{
    /**
     * Resolve the path and "via" from the route.
     *
     * @return array{0: string, 1: ?string}
     */
    protected function resolveRoutePath(Request $request): array
    {
        /** @var Route */
        $route = $request->route();
        $path = $route->getDomain().Str::start($route->uri(), '/');
        $via = $route->getActionName();

        if ($route->named('*livewire.update') && $request->has('components.0.snapshot')) {
            $snapshot = json_decode($request->input('components.0.snapshot'), flags: JSON_THROW_ON_ERROR);

            if (isset($snapshot->memo->path)) {
                $via = 'via '.$path;
                $path = Str::start($snapshot->memo->path, '/');
            }
        }

        return [$path, $via];
    }
}

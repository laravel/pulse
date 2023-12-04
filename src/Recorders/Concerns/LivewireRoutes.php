<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Lang;
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

        if ($route->named('*livewire.update')) {
            $snapshot = json_decode($request->input('components.0.snapshot'), flags: JSON_THROW_ON_ERROR);

            if (isset($snapshot->memo->path)) {
                $via = Lang::get('via ').$path; // @phpstan-ignore-line
                $path = Str::start($snapshot->memo->path, '/');
            }
        }

        return [$path, $via];
    }
}

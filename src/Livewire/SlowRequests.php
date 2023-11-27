<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowRequests as SlowRequestsRecorder;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowRequests extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        $routes = Route::getRoutes()->getRoutesByMethod();

        [$slowRequests, $time, $runAt] = $this->remember(
            fn () => Pulse::aggregate('slow_request', ['max', 'count'], $this->periodAsInterval())
                ->map(function ($row) use ($routes) {
                    [$method, $uri] = explode(' ', $row->key, 2);

                    preg_match('/(.*?)(?:\s\((.*)\))?$/', $uri, $matches);

                    [$uri, $via] = [$matches[1], $matches[2] ?? null];

                    $domain = Str::before($uri, '/');

                    if ($domain) {
                        $uri = '/'.Str::after($uri, '/');
                    }

                    if ($via) {
                        $action = 'via '.$via;
                    } else {
                        $path = $uri === '/' ? $uri : ltrim($uri, '/');
                        $action = ($route = $routes[$method][$domain.$path] ?? null) ? (string) $route->getActionName() : null;
                    }

                    return (object) [
                        'uri' => $domain.$uri,
                        'method' => $method,
                        'action' => $action,
                        'count' => $row->count,
                        'slowest' => $row->max,
                    ];
                })
        );

        return View::make('pulse::livewire.slow-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'slowRequests' => $slowRequests,
            'config' => [
                'threshold' => Config::get('pulse.recorders.'.SlowRequestsRecorder::class.'.threshold'),
                'sample_rate' => Config::get('pulse.recorders.'.SlowRequestsRecorder::class.'.sample_rate'),
            ],
        ]);
    }
}

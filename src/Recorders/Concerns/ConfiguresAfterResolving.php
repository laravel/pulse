<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Closure;
use Illuminate\Foundation\Application;

trait ConfiguresAfterResolving
{
    /**
     * Configure the class after resolving.
     */
    public function afterResolving(Application $app, string $class, Closure $callback): void
    {
        $app->afterResolving($class, $callback);

        if ($app->resolved($class)) {
            $callback($app->make($class));
        }
    }
}

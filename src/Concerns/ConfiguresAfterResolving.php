<?php

namespace Laravel\Pulse\Concerns;

use Closure;
use Illuminate\Contracts\Foundation\Application;

/**
 * @internal
 */
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

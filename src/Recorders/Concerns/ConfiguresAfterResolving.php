<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Illuminate\Foundation\Application;

trait ConfiguresAfterResolving
{
    /**
     * Configure the class after resolving.
     */
    public function afterResolving(Application $app, string $class, callable $callback): void
    {
        $app->afterResolving($class, $callback);

        if ($app->resolved($class)) {
            $callback($app->make($class));
        }
    }
}

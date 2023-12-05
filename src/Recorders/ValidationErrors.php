<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Pulse;
use stdClass;

/**
 * @internal
 */
class ValidationErrors
{
    use Concerns\Ignores,
        Concerns\LivewireRoutes,
        Concerns\Sampling,
        ConfiguresAfterResolving;

    /**
     * The events to listen for.
     *
     * @var list<class-string>
     */
    public array $listen = [
        RequestHandled::class,
    ];

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Record the request.
     */
    public function record(RequestHandled $event): void
    {
        [$request, $response] = [
            $event->request,
            $event->response,
        ];

        $this->pulse->lazy(function () use ($request, $response) {
            $errors = [];

            if (
                ! $request->inertia('X-Inertia') ||
                ! is_array($response->original) ||
                ! (($response->original['props']['errors'] ?? null) instanceof stdClass)
            ) {
                return;
            }

            $errors = (array) $response->original['props']['errors'];
            $names = array_keys($errors);

            [$path, $via] = $this->resolveRoutePath($request);

            foreach ($names as $name) {
                $this->pulse->record(
                    'validation_error',
                    json_encode([$request->method(), $path, $via, $name], flags: JSON_THROW_ON_ERROR),
                )->count();
            }
        });
    }
}

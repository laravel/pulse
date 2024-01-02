<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Laravel\Pulse\Pulse;
use stdClass;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * @internal
 */
class ValidationErrors
{
    use Concerns\Ignores, Concerns\LivewireRoutes, Concerns\Sampling;

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
     * Record validation errors.
     */
    public function record(RequestHandled $event): void
    {
        [$request, $response] = [
            $event->request,
            $event->response,
        ];

        $this->pulse->lazy(function () use ($request, $response) {
            if ($this->shouldSample()) {
                return;
            }

            [$path, $via] = $this->resolveRoutePath($request);

            if ($this->shouldIgnore($path)) {
                return;
            }

            foreach ($this->parseValidationErrors($request, $response) as $name) {
                $this->pulse->record(
                    'validation_error',
                    json_encode([$request->method(), $path, $via, $name], flags: JSON_THROW_ON_ERROR),
                )->count();
            }
        });
    }

    /**
     * Parse validation errors.
     *
     * @return array<int, string>
     */
    protected function parseValidationErrors(Request $request, BaseResponse $response): array
    {
        return $this->parseSessionValidationErrors($request, $response)
            ?? $this->parseJsonValidationErrors($request, $response)
            ?? $this->parseInertiaValidationErrors($request, $response)
            ?? $this->parseUnknownValidationErrors($request, $response)
            ?? [];
    }

    /**
     * Parse session validation errors.
     *
     * @return null|array<int, string>
     */
    protected function parseSessionValidationErrors(Request $request, BaseResponse $response): ?array
    {
        if (
            $response->getStatusCode() !== 302 ||
            ! $request->hasSession() ||
            ! $request->session()->get('errors', null) instanceof ViewErrorBag
        ) {
            return null;
        }

        // TODO: error bags
        return $request->session()->get('errors')->keys();
    }

    /**
     * Parse JSON validation errors.
     *
     * @return null|array<int, string>
     */
    protected function parseJsonValidationErrors(Request $request, BaseResponse $response): ?array
    {
        if (
            $response->getStatusCode() !== 422 ||
            ! $response instanceof JsonResponse ||
            ! is_array($response->original) ||
            ! array_key_exists('errors', $response->original) ||
            ! is_array($response->original['errors']) ||
            ! array_is_list($response->original['errors'])
        ) {
            return null;
        }

        return array_keys($response->original['errors']);
    }

    /**
     * Parse Inertia validation errors.
     *
     * @return null|array<int, string>
     */
    protected function parseInertiaValidationErrors(Request $request, BaseResponse $response): ?array
    {
        if (
            ! $request->header('X-Inertia') ||
            ! $response instanceof JsonResponse ||
            ! is_array($response->original) ||
            ! array_key_exists('props', $response->original) ||
            ! is_array($response->original['props']) ||
            ! array_key_exists('errors', $response->original['props']) ||
            ! $response->original['props']['errors'] instanceof stdClass
        ) {
            return null;
        }

        return array_keys((array) $response->original['props']['errors']);
    }

    /**
     * Parse unknown validation errors.
     *
     * @return null|array<int, string>
     */
    protected function parseUnknownValidationErrors(Request $request, BaseResponse $response): ?array
    {
        if ($response->getStatusCode() !== 422) {
            return null;
        }

        return ['__unknown'];
    }
}

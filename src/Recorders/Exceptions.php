<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Events\ExceptionReported;
use Laravel\Pulse\Pulse;
use Throwable;

/**
 * @internal
 */
class Exceptions
{
    use Concerns\Ignores, Concerns\Sampling, ConfiguresAfterResolving;

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
     * Register the recorder.
     */
    public function register(callable $record, Application $app): void
    {
        $this->afterResolving($app, ExceptionHandler::class, fn (ExceptionHandler $handler) => $handler->reportable(fn (Throwable $e) => $record($e))); // @phpstan-ignore method.notFound

        $this->afterResolving($app, Dispatcher::class, fn (Dispatcher $events) => $events->listen(fn (ExceptionReported $event) => $record($event->exception)));
    }

    /**
     * Record the exception.
     */
    public function record(Throwable $e): void
    {
        $timestamp = CarbonImmutable::now()->getTimestamp();

        $this->pulse->lazy(function () use ($timestamp, $e) {
            $class = $this->resolveClass($e);

            if (! $this->shouldSample() || $this->shouldIgnore($class)) {
                return;
            }

            $location = $this->config->get('pulse.recorders.'.self::class.'.location')
                ? $this->resolveLocation($e)
                : null;

            $this->pulse->record(
                type: 'exception',
                key: json_encode([$class, $location], flags: JSON_THROW_ON_ERROR),
                timestamp: $timestamp,
                value: $timestamp,
            )->max()->count();
        });
    }

    /**
     * Resolve the exception class to record.
     *
     * @return class-string<Throwable>
     */
    protected function resolveClass(Throwable $e): string
    {
        $previous = $e->getPrevious();

        return match (true) {
            $e instanceof \Illuminate\View\ViewException && $previous,
            $e instanceof \Spatie\LaravelIgnition\Exceptions\ViewException && $previous => $previous::class, // @phpstan-ignore class.notFound
            default => $e::class,
        };
    }

    /**
     * Resolve the exception location to record.
     */
    protected function resolveLocation(Throwable $e): string
    {
        return match (true) {
            $e instanceof \Illuminate\View\ViewException => $this->resolveLocationFromViewException($e),
            $e instanceof \Spatie\LaravelIgnition\Exceptions\ViewException => $this->formatLocation($e->getFile(), $e->getLine()), // @phpstan-ignore class.notFound class.notFound class.notFound
            default => $this->resolveLocationFromTrace($e)
        };
    }

    /*
     * Resolve the location of the original view file instead of the cached version.
     */
    protected function resolveLocationFromViewException(Throwable $e): string
    {
        // Getting the line number in the view file is a bit tricky.
        preg_match('/\(View: (?P<path>.*?)\)/', $e->getMessage(), $matches);

        return $this->formatLocation($matches['path'], null);
    }

    /**
     * Resolve the location for the given exception.
     */
    protected function resolveLocationFromTrace(Throwable $e): string
    {
        $firstNonVendorFrame = collect($e->getTrace())
            ->firstWhere(fn (array $frame) => isset($frame['file']) && ! $this->isInternalFile($frame['file']));

        if (! $this->isInternalFile($e->getFile()) || $firstNonVendorFrame === null) {
            return $this->formatLocation($e->getFile(), $e->getLine());
        }

        return $this->formatLocation($firstNonVendorFrame['file'] ?? 'unknown', $firstNonVendorFrame['line'] ?? null);
    }

    /**
     * Determine whether a file should be considered internal.
     */
    protected function isInternalFile(string $file): bool
    {
        return Str::startsWith($file, base_path('vendor'))
            || $file === base_path('artisan')
            || $file === public_path('index.php');
    }

    /**
     * Format a file and line number and strip the base path.
     */
    protected function formatLocation(string $file, ?int $line): string
    {
        return Str::replaceFirst(base_path('/'), '', $file).(is_int($line) ? (':'.$line) : '');
    }
}

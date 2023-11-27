<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
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
    use Concerns\Ignores;
    use Concerns\Sampling;
    use ConfiguresAfterResolving;

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
        $now = new CarbonImmutable();

        $class = $this->getClass($e);

        if (! $this->shouldSample() || $this->shouldIgnore($class)) {
            return;
        }

        $location = $this->config->get('pulse.recorders.'.self::class.'.location') ? $this->getLocation($e) : null;

        $key = json_encode([$class, $location]);

        $this->pulse->record('exception', $key, timestamp: $now, value: $now->getTimestamp())->max();
    }

    /**
     * Get the exception class to record.
     *
     * @return class-string<Throwable>
     */
    protected function getClass(Throwable $e): string
    {
        return match (true) { // @phpstan-ignore return.type
            $e instanceof \Illuminate\View\ViewException,
            $e instanceof \Spatie\LaravelIgnition\Exceptions\ViewException => get_class($e->getPrevious()), // @phpstan-ignore class.notFound class.notFound argument.type
            default => get_class($e),
        };
    }

    /**
     * Get the exception location to record.
     */
    protected function getLocation(Throwable $e): string
    {
        return match (true) {
            $e instanceof \Illuminate\View\ViewException => $this->getLocationFromViewException($e),
            $e instanceof \Spatie\LaravelIgnition\Exceptions\ViewException => $this->formatLocation($e->getFile(), $e->getLine()), // @phpstan-ignore class.notFound class.notFound class.notFound
            default => $this->getLocationFromTrace($e)
        };
    }

    /*
     * Get the location of the original view file instead of the cached version.
     */
    protected function getLocationFromViewException(Throwable $e): string
    {
        // Getting the line number in the view file is a bit tricky.
        preg_match('/\(View: (?P<path>.*?)\)/', $e->getMessage(), $matches);

        return $this->formatLocation($matches['path'], null);
    }

    /**
     * Get the location for the given exception.
     */
    protected function getLocationFromTrace(Throwable $e): string
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

<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Laravel\Pulse\Concerns\ConfiguresAfterResolving;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Events\ExceptionReported;
use Laravel\Pulse\Pulse;
use Throwable;

/**
 * @internal
 */
class Exceptions
{
    use Concerns\Ignores;
    use ConfiguresAfterResolving;

    /**
     * The table to record to.
     */
    public string $table = 'pulse_exceptions';

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
        $this->afterResolving($app, ExceptionHandler::class, fn (ExceptionHandler $handler) => $handler->reportable(fn (Throwable $e) => $record($e)));

        $this->afterResolving($app, Dispatcher::class, fn (Dispatcher $events) => $events->listen(fn (ExceptionReported $event) => $record($event->exception)));
    }

    /**
     * Record the exception.
     */
    public function record(Throwable $e): ?Entry
    {
        $now = new CarbonImmutable();

        [$class, $location] = $this->getDetails($e);

        if ($this->shouldIgnore($class)) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $now->toDateTimeString(),
            'class' => $class,
            'location' => $location,
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Get the exception details.
     *
     * @return array{0: class-string<Throwable>, 1: string}
     */
    protected function getDetails(Throwable $e): array
    {
        return match (true) {
            $e instanceof \Illuminate\View\ViewException => [
                get_class($e->getPrevious()), // @phpstan-ignore argument.type
                $this->getLocationFromViewException($e),
            ],

            $e instanceof \Spatie\LaravelIgnition\Exceptions\ViewException => [ // @phpstan-ignore class.notFound
                get_class($e->getPrevious()), // @phpstan-ignore argument.type, class.notFound
                $this->formatLocation($e->getFile(), $e->getLine()), // @phpstan-ignore class.notFound, class.notFound
            ],

            default => [
                get_class($e),
                $this->getLocation($e),
            ]
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
    protected function getLocation(Throwable $e): string
    {
        $firstNonVendorFrame = collect($e->getTrace())
            ->firstWhere(fn (array $frame) => isset($frame['file']) && $this->isNonVendorFile($frame['file']));

        if ($this->isNonVendorFile($e->getFile()) || $firstNonVendorFrame === null) {
            return $this->formatLocation($e->getFile(), $e->getLine());
        }

        return $this->formatLocation($firstNonVendorFrame['file'], $firstNonVendorFrame['line']);
    }

    /**
     * Determine whether a file is in the vendor directory.
     */
    protected function isNonVendorFile(string $file): bool
    {
        return ! Str::startsWith($file, base_path('vendor'));
    }

    /**
     * Format a file and line number and strip the base path.
     */
    protected function formatLocation(string $file, ?int $line): string
    {
        return Str::replaceFirst(base_path('/'), '', $file).(is_int($line) ? (':'.$line) : '');
    }
}

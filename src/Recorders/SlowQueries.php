<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class SlowQueries
{
    use Concerns\Ignores;
    use Concerns\Sampling;

    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = QueryExecuted::class;

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
     * Record a slow query.
     */
    public function record(QueryExecuted $event): ?Entry
    {
        $now = new CarbonImmutable();

        if ($event->time < $this->config->get('pulse.recorders.'.self::class.'.threshold')) {
            return null;
        }

        if (! $this->shouldSample() || $this->shouldIgnore($event->sql)) {
            return null;
        }

        return (new Entry(
            timestamp: (int) $now->subMilliseconds((int) $event->time)->timestamp,
            type: 'slow_query',
            // TODO: Is this a good separator? Could it collide with something that might appear in a query?
            key: $event->sql.($this->config->get('pulse.recorders.'.self::class.'.location') ? ('::'.$this->getLocation()) : ''),
            value: (int) $event->time,
        ))->count()->max();
    }

    /**
     * Get the location of the query.
     */
    protected function getLocation(): string
    {
        $backtrace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))->skip(2);

        $frame = $backtrace->firstWhere(fn (array $frame) => isset($frame['file']) && ! $this->isInternalFile($frame['file']));

        if ($frame === null) {
            return '';
        }

        return $this->formatLocation($frame['file'] ?? 'unknown', $frame['line'] ?? null);
    }

    /**
     * Determine whether a file should be considered internal.
     */
    protected function isInternalFile(string $file): bool
    {
        return Str::startsWith($file, base_path('vendor/laravel/pulse'))
            || Str::startsWith($file, base_path('vendor/laravel/framework'))
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

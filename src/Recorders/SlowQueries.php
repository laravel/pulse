<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class SlowQueries
{
    use Concerns\Ignores, Concerns\Sampling, Concerns\Thresholds;

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
    public function record(QueryExecuted $event): void
    {
        [$timestampMs, $duration, $sql, $location] = [
            CarbonImmutable::now()->getTimestampMs(),
            (int) $event->time,
            $event->sql,
            $this->config->get('pulse.recorders.'.self::class.'.location')
                ? $this->resolveLocation()
                : null,
        ];

        $this->pulse->lazy(function () use ($timestampMs, $duration, $sql, $location) {
            if (
                ! $this->shouldSample() ||
                $this->shouldIgnore($sql) ||
                $this->underThreshold($duration, $sql)
            ) {
                return;
            }

            if ($maxQueryLength = $this->config->get('pulse.recorders.'.self::class.'.max_query_length')) {
                $sql = Str::limit($sql, $maxQueryLength);
            }

            $this->pulse->record(
                type: 'slow_query',
                key: json_encode([$sql, $location], flags: JSON_THROW_ON_ERROR),
                value: $duration,
                timestamp: (int) (($timestampMs - $duration) / 1000),
            )->max()->count();
        });
    }

    /**
     * Resolve the location of the query.
     */
    protected function resolveLocation(): string
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
        return Str::startsWith($file, base_path('vendor'.DIRECTORY_SEPARATOR.'laravel'.DIRECTORY_SEPARATOR.'pulse'))
            || Str::startsWith($file, base_path('vendor'.DIRECTORY_SEPARATOR.'laravel'.DIRECTORY_SEPARATOR.'framework'))
            || $file === base_path('artisan')
            || $file === public_path('index.php');
    }

    /**
     * Format a file and line number and strip the base path.
     */
    protected function formatLocation(string $file, ?int $line): string
    {
        return Str::replaceFirst(base_path(DIRECTORY_SEPARATOR), '', $file).(is_int($line) ? (':'.$line) : '');
    }
}

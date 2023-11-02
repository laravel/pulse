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
     * The table to record to.
     */
    public string $table = 'pulse_slow_queries';

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

        if ($event->time < $this->config->get('pulse.recorders.'.static::class.'.threshold')) {
            return null;
        }

        if (! $this->shouldSample() || $this->shouldIgnore($event->sql)) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $now->subMilliseconds((int) $event->time)->toDateTimeString(),
            'sql' => $event->sql,
            'location' => $this->getLocation(),
            'duration' => (int) $event->time,
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Get the location of the query.
     */
    protected function getLocation(): string
    {
        $backtrace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

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

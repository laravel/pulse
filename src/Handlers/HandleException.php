<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Support\Str;
use Laravel\Pulse\RedisAdapter;
use Throwable;

class HandleException
{
    /**
     * Handle an exception.
     */
    public function __invoke(Throwable $e): void
    {
        try {
            $this->recordException($e);
        } catch (Throwable) {
            // TODO: What should we do with the new exception?
        }
    }

    /**
     * Record the exception.
     */
    protected function recordException(Throwable $e)
    {
        $keyDate = now()->format('Y-m-d');
        $keyExpiry = now()->toImmutable()->startOfDay()->addDays(7)->timestamp;

        $exception = json_encode([
            'class' => get_class($e),
            'location' => $this->getLocation($e),
        ]);

        $countKey = "pulse_exception_counts:{$keyDate}";
        RedisAdapter::zincrby($countKey, 1, $exception);
        RedisAdapter::expireat($countKey, $keyExpiry, 'NX');

        $lastOccurrenceKey = "pulse_exception_last_occurrences:{$keyDate}";
        RedisAdapter::zadd($lastOccurrenceKey, now()->timestamp, $exception);
        RedisAdapter::expireat($lastOccurrenceKey, $keyExpiry, 'NX');
    }

    /**
     * Get the location for the given exception.
     */
    protected function getLocation(Throwable $e): string
    {
        // TODO: has issue when exception occurs in Blade/Livewire view.
        $firstNonVendorFrame = collect($e->getTrace())
            ->firstWhere(fn ($frame) => isset($frame['file']) && $this->isNonVendorFile($frame['file']));

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
     * Format a file and line number and strip the base base.
     */
    protected function formatLocation(string $file, int $line): string
    {
        return Str::replaceFirst(base_path(), '', $file).':'.$line;
    }
}

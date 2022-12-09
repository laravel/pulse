<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class HandleException
{
    /**
     * Handle an exception.
     */
    public function __invoke(Throwable $e): void
    {
        $keyDate = now()->format('Y-m-d');
        $keyExpiry = now()->toImmutable()->startOfDay()->addDays(7)->timestamp;
        $keyPrefix = config('database.redis.options.prefix');

        $exception = json_encode([
            'class' => get_class($e),
            'location' => $this->getLocation($e),
        ]);

        $countKey = "pulse_exception_counts:{$keyDate}";
        Redis::zIncrBy($countKey, 1, $exception);
        Redis::rawCommand('EXPIREAT', $keyPrefix.$countKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7

        $lastOccurrenceKey = "pulse_exception_last_occurrences:{$keyDate}";
        Redis::zAdd($lastOccurrenceKey, now()->timestamp, $exception);
        Redis::rawCommand('EXPIREAT', $keyPrefix.$lastOccurrenceKey, $keyExpiry, 'NX'); // TODO: phpredis expireAt doesn't support 'NX' in 5.3.7
    }

    /**
     * Get the location for the given exception.
     */
    protected function getLocation(Throwable $e): string
    {
        if ($this->thrownByHelper($e)) {
            return $this->formatLocation($e->getTrace()[0]['file'], $e->getTrace()[0]['line']);
        }

        return $this->formatLocation($e->getFile(), $e->getLine());
    }

    /**
     * Format a file and line number and strip the base base.
     */
    protected function formatLocation(string $file, int $line): string
    {
        return Str::replaceFirst(base_path(), '', $file).':'.$line;
    }

    /**
     * Determine whether the exception was thrown by a helper.
     */
    protected function thrownByHelper(Throwable $e): bool
    {
        return in_array(
            $e->getTrace()[0]['function'] ?? null,
            ['throw_if', 'throw_unless']
        );
    }
}

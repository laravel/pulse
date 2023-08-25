<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;
use Throwable;

/**
 * @internal
 */
class Exceptions
{
    /**
     * The table to record to.
     */
    public string $table = 'pulse_exceptions';

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Register the recorder.
     */
    public function register(callable $record, ExceptionHandler $handler): void
    {
        $handler->reportable(fn (Throwable $e) => $record($e));
    }

    /**
     * Record the exception.
     */
    public function record(Throwable $e): Entry
    {
        $now = new CarbonImmutable();

        return new Entry($this->table, [
            'date' => $now->toDateTimeString(),
            'class' => $e::class,
            'location' => $this->getLocation($e),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Get the location for the given exception.
     */
    protected function getLocation(Throwable $e): string
    {
        // TODO: has issue when exception occurs in Blade/Livewire view.
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
     * Format a file and line number and strip the base base.
     */
    protected function formatLocation(string $file, int $line): string
    {
        return Str::replaceFirst(base_path(), '', $file).':'.$line;
    }
}

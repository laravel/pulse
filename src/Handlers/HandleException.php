<?php

namespace Laravel\Pulse\Handlers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;
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
        DB::table('pulse_exceptions')->insert([
            'date' => now()->toDateTimeString(),
            'user_id' => Auth::id(),
            'class' => get_class($e),
            'location' => $this->getLocation($e),
        ]);

        // Lottery::odds(1, 100)->winner(fn () =>
        //     DB::table('pulse_exceptions')->where('date', '<', now()->subDays(7)->toDateTimeString())->delete()
        // )->choose();
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

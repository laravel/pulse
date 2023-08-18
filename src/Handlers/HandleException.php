<?php

namespace Laravel\Pulse\Handlers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Str;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Pulse;
use Throwable;

class HandleException
{
    /**
     * Create a new handler instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected AuthManager $auth,
    ) {
        //
    }

    /**
     * Handle an exception.
     */
    public function __invoke(Throwable $e): void
    {
        $this->pulse->rescue(function () use ($e) {
            $now = new CarbonImmutable();

            $this->pulse->record(new Entry('pulse_exceptions', [
                'date' => $now->toDateTimeString(),
                'class' => $e::class,
                'location' => $this->getLocation($e),
                'user_id' => $this->auth->hasUser()
                    ? $this->auth->id()
                    : fn () => $this->auth->id(),
            ]));
        });
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

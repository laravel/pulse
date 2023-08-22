<?php

namespace Laravel\Pulse\Livewire\Concerns;

use Laravel\Pulse\Pulse;

trait ShouldNotReportUsage
{
    /**
     * Disable recording when the component is booted.
     */
    public function bootShouldNotReportUsage(): void
    {
        app(Pulse::class)->stopRecording();
    }
}

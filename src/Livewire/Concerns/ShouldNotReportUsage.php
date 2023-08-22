<?php

namespace Laravel\Pulse\Livewire\Concerns;

use Laravel\Pulse\Facades\Pulse;

trait ShouldNotReportUsage
{
    /**
     * Disable recording when the component is booted.
     */
    public function bootShouldNotReportUsage(): void
    {
        Pulse::stopRecording();
    }
}

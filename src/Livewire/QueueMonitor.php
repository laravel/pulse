<?php

namespace Laravel\Pulse\Livewire;

use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class QueueMonitor extends Component
{
    use HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * The queue to monitor.
     */
    public string $queue = 'default';

    /**
     * The connection.
     */
    public ?string $connection = null;

    public function render(callable $query)
    {
        [$readings, $time, $runAt] = dd($this->remember(fn ($interval) => $query($interval, $this->queue, $this->connection)));
    }
}

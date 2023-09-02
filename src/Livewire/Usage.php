<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasColumns;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Attributes\Url;
use Livewire\Component;

class Usage extends Component
{
    use HasColumns, HasPeriod, RemembersQueries, ShouldNotReportUsage;

    /**
     * The type of usage to show.
     *
     * @var 'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'|null
     */
    public ?string $type = null;

    /**
     * The usage type.
     *
     * @var 'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'
     */
    #[Url]
    public string $usage = 'request_counts';

    /**
     * Render the component.
     */
    public function render(callable $query): Renderable
    {
        $type = $this->type ?? $this->usage;

        [$userRequestCounts, $time, $runAt] = $this->remember(fn ($interval) => $query($interval, $type), $type);

        return View::make('pulse::livewire.usage', [
            'time' => $time,
            'runAt' => $runAt,
            'userRequestCounts' => $userRequestCounts,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', ['class' => 'col-span-3']);
    }
}

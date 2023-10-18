<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\Usage as UsageQuery;
use Laravel\Pulse\Recorders\Jobs;
use Laravel\Pulse\Recorders\Requests;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class Usage extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

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
    public function render(UsageQuery $query): Renderable
    {
        $type = $this->type ?? $this->usage;

        [$userRequestCounts, $time, $runAt] = $this->remember(fn ($interval) => $query($interval, $type), $type);

        return View::make('pulse::livewire.usage', [
            'time' => $time,
            'runAt' => $runAt,
            'requestsConfig' => Config::get('pulse.recorders.'.Requests::class),
            'jobsConfig' => Config::get('pulse.recorders.'.Jobs::class),
            'userRequestCounts' => $userRequestCounts,
        ]);
    }
}

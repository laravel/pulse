<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\Usage as UsageQuery;
use Laravel\Pulse\Recorders\Jobs;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserRequests;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class Usage extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * The type of usage to show.
     *
     * @var 'requests'|'slow_requests'|'jobs'|null
     */
    public ?string $type = null;

    /**
     * The usage type.
     *
     * @var 'requests'|'slow_requests'|'jobs'
     */
    #[Url]
    public string $usage = 'requests';

    /**
     * Render the component.
     */
    public function render(UsageQuery $query): Renderable
    {
        $type = $this->type ?? $this->usage;

        // [$userRequestCounts, $time, $runAt] = $this->remember(fn ($interval) => $query($interval, $type), $type);
        $userRequestCounts = $query($this->periodAsInterval(), $type);
        $time = 0;
        $runAt = 0;

        return View::make('pulse::livewire.usage', [
            'time' => $time,
            'runAt' => $runAt,
            'userRequestsConfig' => Config::get('pulse.recorders.'.UserRequests::class),
            'slowRequestsConfig' => Config::get('pulse.recorders.'.SlowRequests::class),
            'jobsConfig' => Config::get('pulse.recorders.'.Jobs::class),
            'userRequestCounts' => $userRequestCounts,
        ]);
    }
}

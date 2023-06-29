<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class SlowOutgoingRequests extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        if (request()->hasHeader('X-Livewire')) {
            $this->loadData();
        }

        [$slowOutgoingRequests, $time, $runAt] = $this->slowOutgoingRequests();

        return view('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'slowOutgoingRequests' => $slowOutgoingRequests,
            'initialDataLoaded' => $slowOutgoingRequests !== null,
        ]);
    }

    /**
     * The slow outgoing requests.
     */
    protected function slowOutgoingRequests(): array
    {
        return Cache::get("pulse:slow-outgoing-requests:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     */
    public function loadData(): void
    {
        Cache::remember("pulse:slow-outgoing-requests:{$this->period}", $this->periodCacheDuration(), function () {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowOutgoingRequests = DB::table('pulse_outgoing_requests')
                ->selectRaw('`uri`, COUNT(*) as count, MAX(duration) AS slowest')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->where('duration', '>=', config('pulse.slow_outgoing_request_threshold'))
                ->groupBy('uri')
                ->orderByDesc('slowest')
                ->get()
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowOutgoingRequests, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('slow-outgoing-requests:dataLoaded');
    }
}

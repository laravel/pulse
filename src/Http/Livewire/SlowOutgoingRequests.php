<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsSlowOutgoingRequests;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;
use RuntimeException;

class SlowOutgoingRequests extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * Render the component.
     */
    public function render(Storage $storage): Renderable
    {
        [$slowOutgoingRequests, $time, $runAt] = $this->slowOutgoingRequests($storage);

        $this->dispatch('slow-outgoing-requests:dataLoaded');

        return view('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'slowOutgoingRequests' => $slowOutgoingRequests,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return view('pulse::components.placeholder', ['class' => 'col-span-3']);
    }

    /**
     * The slow outgoing requests.
     */
    protected function slowOutgoingRequests(Storage $storage): array
    {
        if (! $storage instanceof SupportsSlowOutgoingRequests) {
            throw new RuntimeException('Storage driver does not support slow outgoing requests.');
        }

        return Cache::remember("illuminate:pulse:slow-outgoing-requests:{$this->period}", $this->periodCacheDuration(), function () use ($storage) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowOutgoingRequests = $storage->slowOutgoingRequests($this->periodAsInterval());

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowOutgoingRequests, $time, $now->toDateTimeString()];
        });
    }
}

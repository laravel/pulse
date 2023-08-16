<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsSlowOutgoingRequests;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;
use RuntimeException;

class SlowOutgoingRequests extends Component
{
    use HasPeriod;
    use ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(Storage $storage): Renderable
    {
        if (! $storage instanceof SupportsSlowOutgoingRequests) {
            // TODO return an "unsupported" card.
            throw new RuntimeException('Storage driver does not support slow outgoing requests.');
        }

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
    protected function slowOutgoingRequests(Storage&SupportsSlowOutgoingRequests $storage): array
    {
        return Cache::remember("illuminate:pulse:slow-outgoing-requests:{$this->period}", $this->periodCacheDuration(), function () use ($storage) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowOutgoingRequests = $storage->slowOutgoingRequests($this->periodAsInterval());

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowOutgoingRequests, $time, $now->toDateTimeString()];
        });
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class SlowQueries extends Component
{
    use HasPeriod;
    use ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(Storage $storage): Renderable
    {
        [$slowQueries, $time, $runAt] = $this->slowQueries($storage);

        $this->dispatch('slow-queries:dataLoaded');

        return view('pulse::livewire.slow-queries', [
            'time' => $time,
            'runAt' => $runAt,
            'slowQueries' => $slowQueries,
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
     * The slow queries.
     */
    protected function slowQueries($storage): array
    {
        return Cache::remember("illuminate:pulse:slow-queries:{$this->period}", $this->periodCacheDuration(), function () use ($storage) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowQueries = $storage->slowQueries($this->periodAsInterval());

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowQueries, $time, $now->toDateTimeString()];
        });
    }
}

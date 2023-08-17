<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class SlowQueries extends Component
{
    use HasPeriod, ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(callable $query): Renderable
    {
        [$slowQueries, $time, $runAt] = $this->slowQueries($query);

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
    protected function slowQueries(callable $query): array
    {
        return Cache::remember("illuminate:pulse:slow-queries:{$this->period}", $this->periodCacheDuration(), function () use ($query) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowQueries = $query($this->periodAsInterval());

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowQueries, $time, $now->toDateTimeString()];
        });
    }
}

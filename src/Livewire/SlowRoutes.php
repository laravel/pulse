<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsSlowRoutes;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;
use RuntimeException;

class SlowRoutes extends Component
{
    use HasPeriod;
    use ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(Storage $storage): Renderable
    {
        if (! $storage instanceof SupportsSlowRoutes) {
            // TODO return an "unsupported" card.
            throw new RuntimeException('Storage driver does not support slow routes.');
        }

        [$slowRoutes, $time, $runAt] = $this->slowRoutes($storage);

        $this->dispatch('slow-routes:dataLoaded');

        return view('pulse::livewire.slow-routes', [
            'time' => $time,
            'runAt' => $runAt,
            'slowRoutes' => $slowRoutes,
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
     * The slow routes.
     */
    protected function slowRoutes(Storage|SupportsSlowRoutes $storage): array
    {
        return Cache::remember("illuminate:pulse:slow-routes:{$this->period}", $this->periodCacheDuration(), function () use ($storage) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowRoutes = $storage->slowRoutes($this->periodAsInterval());

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowRoutes, $time, $now->toDateTimeString()];
        });
    }
}

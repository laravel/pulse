<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Attributes\Url;
use Livewire\Component;

class Exceptions extends Component
{
    use HasPeriod, ShouldNotReportUsage;

    /**
     * The view type
     *
     * @var 'count'|'last_occurrence'
     */
    #[Url(as: 'exceptions_by')]
    public string $orderBy = 'count';

    /**
     * Render the component.
     */
    public function render(callable $query): Renderable
    {
        [$exceptions, $time, $runAt] = $this->exceptions($query);

        $this->dispatch('exceptions:dataLoaded');

        return View::make('pulse::livewire.exceptions', [
            'time' => $time,
            'runAt' => $runAt,
            'exceptions' => $exceptions,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', ['class' => 'col-span-3']);
    }

    /**
     * The exceptions.
     */
    protected function exceptions(callable $query): array
    {
        return Cache::remember("illuminate:pulse:exceptions:{$this->orderBy}:{$this->period}", $this->periodCacheDuration(), function () use ($query) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $exceptions = $query($this->periodAsInterval(), match ($this->orderBy) {
                'last_occurrence' => 'last_occurrence',
                default => 'count'
            });

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$exceptions, $time, $now->toDateTimeString()];
        });
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsExceptions;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

class Exceptions extends Component
{
    use HasPeriod;
    use ShouldNotReportUsage;

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
    public function render(Storage $storage): Renderable
    {
        if (! $storage instanceof SupportsExceptions) {
            // TODO return an "unsupported" card.
            throw new RuntimeException('Storage driver does not support exceptions.');
        }

        [$exceptions, $time, $runAt] = $this->exceptions($storage);

        $this->dispatch('exceptions:dataLoaded');

        return view('pulse::livewire.exceptions', [
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
        return view('pulse::components.placeholder', ['class' => 'col-span-3']);
    }

    /**
     * The exceptions.
     */
    protected function exceptions(Storage&SupportsExceptions $storage): array
    {
        return Cache::remember("illuminate:pulse:exceptions:{$this->orderBy}:{$this->period}", $this->periodCacheDuration(), function () use ($storage) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $exceptions = $storage->exceptions($this->periodAsInterval(), match ($this->orderBy) {
                'last_occurrence' => 'last_occurrence',
                default => 'count'
            });

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$exceptions, $time, $now->toDateTimeString()];
        });
    }
}

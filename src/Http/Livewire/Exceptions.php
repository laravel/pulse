<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsExceptions;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

class Exceptions extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

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
    protected function exceptions(Storage $storage): array
    {
        if (! $storage instanceof SupportsExceptions) {
            throw new RuntimeException('Storage driver does not support exceptions.');
        }

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

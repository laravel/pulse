<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Attributes\Url;
use Livewire\Component;

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
    public function render(): Renderable
    {
        [$exceptions, $time, $runAt] = $this->exceptions();

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
    protected function exceptions(): array
    {
        return Cache::remember("illuminate:pulse:exceptions:{$this->orderBy}:{$this->period}", $this->periodCacheDuration(), function () {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $exceptions = DB::table('pulse_exceptions')
                ->selectRaw('class, location, COUNT(*) AS count, MAX(date) AS last_occurrence')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->groupBy('class', 'location')
                ->orderByDesc(match ($this->orderBy) {
                    'last_occurrence' => 'last_occurrence',
                    default => 'count'
                })
                ->get()
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$exceptions, $time, $now->toDateTimeString()];
        });
    }
}

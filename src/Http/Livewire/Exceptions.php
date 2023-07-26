<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class Exceptions extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * The view type
     *
     * @var 'count'|'last_occurrence'|null
     */
    public ?string $orderBy = null;

    /**
     * The query string parameters.
     *
     * @var array
     */
    protected $queryString = [
        'orderBy' => ['except' => 'count', 'as' => 'exceptions_by'],
    ];

    /**
     * Handle the mount event.
     */
    public function mount(): void
    {
        $this->orderBy = $this->orderBy ?: 'count';
    }

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        if (request()->hasHeader('X-Livewire')) {
            $this->loadData();
        }

        [$exceptions, $time, $runAt] = $this->exceptions();

        return view('pulse::livewire.exceptions', [
            'time' => $time,
            'runAt' => $runAt,
            'exceptions' => $exceptions,
            'initialDataLoaded' => $exceptions !== null,
        ]);
    }

    /**
     * The exceptions.
     */
    protected function exceptions(): array
    {
        return Cache::get("illuminate:pulse:exceptions:{$this->orderBy}:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     */
    public function loadData(): void
    {
        Cache::remember("illuminate:pulse:exceptions:{$this->orderBy}:{$this->period}", $this->periodCacheDuration(), function () {
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

        $this->dispatchBrowserEvent('exceptions:dataLoaded');
    }
}

<?php

namespace Laravel\Pulse\Http\Livewire;

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
    public $orderBy;

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
     *
     * @return void
     */
    public function mount()
    {
        $this->orderBy = $this->orderBy ?: 'count';
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        if (request()->hasHeader('X-Livewire')) {
            $this->loadData();
        }

        [$exceptions, $time, $runAt] = $this->exceptions();

        return view('pulse::livewire.exceptions', [
            'time' => $time,
            'runAt' => $runAt,
            'exceptions' => $exceptions,
            'initialDataLoaded' => $exceptions !== null
        ]);
    }

    /**
     * The exceptions.
     *
     * @return array
     */
    protected function exceptions()
    {
        return Cache::get("pulse:exceptions:{$this->orderBy}:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     *
     * @return void
     */
    public function loadData()
    {
        Cache::remember("pulse:exceptions:{$this->orderBy}:{$this->period}", now()->addSeconds(match ($this->period) {
            '6_hours' => 30,
            '24_hours' => 60,
            '7_days' => 600,
            default => 5,
        }), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $exceptions = DB::table('pulse_exceptions')
                ->selectRaw('class, location, COUNT(*) AS count, MAX(date) AS last_occurrence')
                ->where('date', '>=', $now->subHours(match ($this->period) {
                    '6_hours' => 6,
                    '24_hours' => 24,
                    '7_days' => 168,
                    default => 1,
                })->toDateTimeString())
                ->groupBy('class', 'location')
                ->orderByDesc(match ($this->orderBy) {
                    'last_occurrence' => 'last_occurrence',
                    default => 'count'
                })
                ->limit(10)
                ->get();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$exceptions, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('exceptions:dataLoaded');
    }
}

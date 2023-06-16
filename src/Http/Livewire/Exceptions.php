<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class Exceptions extends Component implements ShouldNotReportUsage
{
    /**
     * The view type
     *
     * @var 'count'|'last_occurrence'|null
     */
    public $exception;

    /**
     * The usage period.
     *
     * @var '1-hour'|6-hours'|'24-hours'|'7-days'|null
     */
    public $period;

    /**
     * The event listeners.
     *
     * @var array
     */
    protected $listeners = [
        'periodChanged',
    ];

    /**
     * Handle the mount event.
     *
     * @return void
     */
    public function mount()
    {
        $this->period = request()->query('period') ?: '1-hour';

        $this->exception = $this->exception ?: 'count';
    }

    /**
     * Handle the periodChanged event.
     *
     * @param  string  $period
     * @return void
     */
    public function periodChanged($period)
    {
        $this->period = $period;
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
        return Cache::get("pulse:exceptions:{$this->exception}:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     *
     * @return void
     */
    public function loadData()
    {
        Cache::remember("pulse:exceptions:{$this->exception}:{$this->period}", now()->addSeconds(match ($this->period) {
            '6-hours' => 30,
            '24-hours' => 60,
            '7-days' => 600,
            default => 5,
        }), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $exceptions = DB::table('pulse_exceptions')
                ->selectRaw('class, location, COUNT(*) AS count, MAX(date) AS last_occurrence')
                ->where('date', '>=', $now->subHours(match ($this->period) {
                    '6-hours' => 6,
                    '24-hours' => 24,
                    '7-days' => 168,
                    default => 1,
                })->toDateTimeString())
                ->groupBy('class', 'location')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$exceptions, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('exceptions:dataLoaded');
    }
}

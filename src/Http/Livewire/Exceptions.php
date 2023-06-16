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
     * @var 'count'|'last_occurrence'
     */
    public $exception;

    /**
     * The usage period.
     *
     * @var string
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

        return view('pulse::livewire.exceptions', [
            'exceptions' => $exceptions = $this->exceptions(),
            'initialDataLoaded' => $exceptions !== null
        ]);
    }

    /**
     * The exceptions.
     *
     * @return array|null
     */
    protected function exceptions()
    {
        return Cache::get("pulse:exceptions:{$this->exception}:{$this->period}");
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
            // TODO: here for debugging the loading indicators
            $start = hrtime(true);

            $exceptions = DB::table('pulse_exceptions')
                ->selectRaw('class, location, COUNT(*) AS count, MAX(date) AS last_occurrence')
                ->where('date', '>=', now()->subHours(match ($this->period) {
                    '6-hours' => 6,
                    '24-hours' => 24,
                    '7-days' => 168,
                    default => 1,
                })->toDateTimeString())
                ->groupBy('class', 'location')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $this->dispatchBrowserEvent('exceptions:dataCached', [
                'time' => (int) ((hrtime(true) - $start) / 1000000),
                'key' => "pulse:exceptions:{$this->exception}:{$this->period}",
            ]);

            return $exceptions;
        });

        $this->dispatchBrowserEvent('exceptions:dataLoaded');
    }
}

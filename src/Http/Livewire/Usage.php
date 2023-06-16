<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class Usage extends Component implements ShouldNotReportUsage
{
    /**
     * The usage type.
     *
     * @var string
     */
    public $usage;

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
     * The query string parameters.
     *
     * @var array
     */
    protected $queryString = [
        'usage' => ['except' => 'request-counts'],
    ];

    /**
     * Handle the mount event.
     *
     * @return void
     */
    public function mount()
    {
        $this->period = request()->query('period') ?: '1-hour';

        $this->usage = $this->usage ?: 'request-counts';
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

        [$userRequestCounts, $time, $runAt] = $this->userRequestCounts();

        return view('pulse::livewire.usage', [
            'time' => $time,
            'runAt' => $runAt,
            'userRequestCounts' => $userRequestCounts,
            'initialDataLoaded' => $userRequestCounts !== null,
        ]);
    }

    /**
     * The user request counts.
     *
     * @return array
     */
    protected function userRequestCounts()
    {
        return Cache::get("pulse:usage:{$this->usage}:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     *
     * @return void
     */
    public function loadData()
    {
        Cache::remember("pulse:usage:{$this->usage}:{$this->period}", now()->addSeconds(match ($this->period) {
            '6-hours' => 30,
            '24-hours' => 60,
            '7-days' => 600,
            default => 5,
        }), function () {
            $now = now();

            $runAt = now()->toDateTimeString();

            $start = hrtime(true);

            $top10 = DB::table('pulse_requests')
                ->selectRaw('user_id, COUNT(*) as count')
                ->whereNotNull('user_id')
                ->where('date', '>=', $now->subHours(match ($this->period) {
                    '6-hours' => 6,
                    '24-hours' => 24,
                    '7-days' => 168,
                    default => 1,
                })->toDateTimeString())
                ->when($this->usage === 'slow-endpoint-counts', fn ($query) => $query->where('duration', '>=', config('pulse.slow_endpoint_threshold')))
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            // TODO: extract to user customisable resolver.
            $users = User::findMany($top10->pluck('user_id'));

            $userRequestCounts = $top10
                ->map(function ($row) use ($users) {
                    $user = $users->firstWhere('id', $row->user_id);

                    return $user ? [
                        'count' => $row->count,
                        'user' => $user->setVisible(['name', 'email']),
                    ] : null;
                })
                ->filter()
                ->values()
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$userRequestCounts, $time, $runAt];
        });

        $this->dispatchBrowserEvent('usage:dataLoaded');
    }
}

<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class Usage extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * The usage type.
     *
     * @var 'request_counts'|'slow_endpoint_counts'|'dispatched_job_count'|null
     */
    public ?string $usage = null;

    /**
     * The query string parameters.
     *
     * @var array
     */
    protected $queryString = [
        'usage' => ['except' => 'request_counts'],
    ];

    /**
     * Handle the mount event.
     */
    public function mount(): void
    {
        $this->usage = $this->usage ?: 'request_counts';
    }

    /**
     * Render the component.
     */
    public function render(): Renderable
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
     */
    protected function userRequestCounts(): array
    {
        return Cache::get("pulse:usage:{$this->usage}:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     */
    public function loadData(): void
    {
        Cache::remember("pulse:usage:{$this->usage}:{$this->period}", $this->periodCacheDuration(), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $top10 = DB::query()
                ->when($this->usage === 'dispatched_job_count', function ($query) {
                    $query->from('pulse_jobs');
                }, function ($query) {
                    $query->from('pulse_requests');
                })
                ->selectRaw('user_id, COUNT(*) as count')
                ->whereNotNull('user_id')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->when($this->usage === 'slow_endpoint_counts', fn ($query) => $query->where('duration', '>=', config('pulse.slow_endpoint_threshold')))
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

            return [$userRequestCounts, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('usage:dataLoaded');
    }
}

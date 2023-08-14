<?php

namespace Laravel\Pulse\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Attributes\Url;
use Livewire\Component;

class Usage extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * The type of usage to show.
     *
     * @var 'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'|null
     */
    public ?string $type = null;

    /**
     * The usage type.
     *
     * @var 'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'
     */
    #[Url]
    public string $usage = 'request_counts';

    /**
     * Get the type of usage to display.
     *
     * @return 'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'
     */
    public function getType(): string
    {
        return $this->type ?? $this->usage;
    }

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$userRequestCounts, $time, $runAt] = $this->userRequestCounts();

        $this->dispatch('usage:'.($this->type ? "{$this->type}:" : '').'dataLoaded');

        return view('pulse::livewire.usage', [
            'time' => $time,
            'runAt' => $runAt,
            'userRequestCounts' => $userRequestCounts,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder()
    {
        return view('pulse::components.placeholder', ['class' => 'col-span-3']);
    }

    /**
     * The user request counts.
     */
    protected function userRequestCounts(): array
    {
        return Cache::remember("illuminate:pulse:usage:{$this->getType()}:{$this->period}", $this->periodCacheDuration(), function () {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $top10 = DB::query()
                ->when($this->getType() === 'dispatched_job_counts', function ($query) {
                    $query->from('pulse_jobs');
                }, function ($query) {
                    $query->from('pulse_requests');
                })
                ->selectRaw('user_id, COUNT(*) as count')
                ->whereNotNull('user_id')
                ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
                ->when($this->getType() === 'slow_endpoint_counts', fn ($query) => $query->where('duration', '>=', config('pulse.slow_endpoint_threshold')))
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $users = Pulse::resolveUsers($top10->pluck('user_id'));

            $userRequestCounts = $top10
                ->map(function ($row) use ($users) {
                    $user = $users->firstWhere('id', $row->user_id);

                    return $user ? [
                        'count' => $row->count,
                        'user' => [
                            'name' => $user['name'],
                            // "extra" rather than 'email'
                            // avatar for pretty-ness?
                            'email' => $user['email'],
                        ],
                    ] : null;
                })
                ->filter()
                ->values()
                ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$userRequestCounts, $time, $now->toDateTimeString()];
        });
    }
}

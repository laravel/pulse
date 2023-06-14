<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Livewire\Component;

class Usage extends Component implements ShouldNotReportUsage
{
    public $usage = 'request-counts';

    public $period;

    public $readyToLoad = false;

    protected $listeners = ['periodChanged'];

    protected $queryString = [
        'usage' => ['except' => 'request-counts'],
    ];

    public function mount()
    {
        $this->period = request()->query('period') ?? '1-hour';
    }

    public function loadData()
    {
        $this->readyToLoad = true;
    }

    public function render()
    {
        if (! $this->readyToLoad) {
            return view('pulse::livewire.usage', [
                'loading' => true,
                'userRequestCounts' => null,
                'time' => 0,
            ]);
        }

        $from = now()->subHours(match ($this->period) {
            '6-hours' => 6,
            '24-hours' => 24,
            '7-days' => 168,
            default => 1,
        });

        [$userRequestCounts, $time] = Cache::remember(
            'pulse:usage:' . $this->usage . ':' . ($this->period ?? '1-hour'),
            now()->addSeconds(match ($this->period) {
                '6-hours' => 30,
                '24-hours' => 60,
                '7-days' => 600,
                default => 5,
            }),
            function () use ($from) {
                $start = hrtime(true);

                $top10 = DB::table('pulse_requests')
                    ->selectRaw('user_id, COUNT(*) as count')
                    ->whereNotNull('user_id')
                    ->where('date', '>=', $from->toDateTimeString())
                    ->when($this->usage === 'slow-endpoint-counts', function ($builder) {
                        $builder->where('duration', '>=', config('pulse.slow_endpoint_threshold'));
                    })
                    ->groupBy('user_id')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();

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
                    ->values();

                $time = (hrtime(true) - $start) / 1000000;

                return [$userRequestCounts, $time];
            }
        );

        $this->dispatchBrowserEvent('loaded');

        return view('pulse::livewire.usage', [
            'loading' => false,
            'userRequestCounts' => $userRequestCounts,
            'time' => $time,
        ]);
    }

    public function periodChanged(string $period)
    {
        $this->period = $period;
    }
}

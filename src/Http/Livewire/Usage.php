<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Pulse;
use Livewire\Component;

class Usage extends Component implements ShouldNotReportUsage
{
    public $usage = 'request-counts';

    public $period;

    protected $listeners = ['periodChanged'];

    protected $queryString = [
        'usage' => ['except' => 'request-counts'],
    ];

    public function mount()
    {
        $this->period = request()->query('period') ?? '1-hour';
    }

    public function render(Pulse $pulse)
    {
        $from = now()->subHours(match ($this->period) {
            '6-hours' => 6,
            '24-hours' => 24,
            '7-days' => 168,
            default => 1,
        });

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

        return view('pulse::livewire.usage', [
            'userRequestCounts' => $userRequestCounts,
        ]);
    }

    public function periodChanged(string $period)
    {
        $this->period = $period;
    }
}

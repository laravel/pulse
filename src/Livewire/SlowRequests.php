<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Recorders\Concerns\Thresholds;
use Laravel\Pulse\Recorders\SlowRequests as SlowRequestsRecorder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * @internal
 */
#[Lazy]
class SlowRequests extends Card
{
    use Thresholds;

    /**
     * Ordering.
     *
     * @var 'slowest'|'count'|'average'|'total'
     */
    #[Url(as: 'slow-requests')]
    public string $orderBy = 'slowest';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$slowRequests, $time, $runAt] = $this->remember(
            fn () => $this->aggregate(
                'slow_request',
                ['max', 'count', 'avg'],
                match ($this->orderBy) {
                    'count' => 'count',
                    'average' => 'avg',
                    'total' => 'avg_count',
                    default => 'max',
                },
            )
            ->map(function ($row) {
                [$method, $uri, $action] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                return (object) [
                    'uri' => $uri,
                    'method' => $method,
                    'action' => $action,
                    'count' => $row->count,
                    'slowest' => $row->max,
                    'avg' => $row->avg,
                    'total' => $row->avg_count,
                    'threshold' => $this->threshold($uri, SlowRequestsRecorder::class),
                ];
            }),
            $this->orderBy,
        );

        return View::make('pulse::livewire.slow-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'slowRequests' => $slowRequests,
            'config' => [
                'threshold' => Config::get('pulse.recorders.'.SlowRequestsRecorder::class.'.threshold'),
                'sample_rate' => Config::get('pulse.recorders.'.SlowRequestsRecorder::class.'.sample_rate'),
            ],
        ]);
    }
}

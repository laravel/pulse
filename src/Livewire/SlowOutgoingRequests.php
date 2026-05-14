<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Recorders\Concerns\Thresholds;
use Laravel\Pulse\Recorders\SlowOutgoingRequests as SlowOutgoingRequestsRecorder;
use Laravel\Pulse\Structs\SlowOutgoingRequestStruct;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * @internal
 */
#[Lazy]
class SlowOutgoingRequests extends Card
{
    use Thresholds;

    /**
     * Ordering.
     *
     * @var 'slowest'|'count'
     */
    #[Url(as: 'slow-outgoing-requests')]
    public string $orderBy = 'slowest';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$slowOutgoingRequests, $time, $runAt] = $this->remember(
            fn () => $this->aggregate(
                'slow_outgoing_request',
                ['max', 'count'],
                match ($this->orderBy) {
                    'count' => 'count',
                    default => 'max',
                },
            )->map(function ($row) {
                [$method, $uri] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                return new SlowOutgoingRequestStruct(
                    method: $method,
                    uri: $uri,
                    slowest: $row->max,
                    count: $row->count,
                    threshold: $this->threshold($uri, SlowOutgoingRequestsRecorder::class),
                );
            }),
            $this->orderBy,
        );

        return View::make('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.SlowOutgoingRequestsRecorder::class),
            'slowOutgoingRequests' => $slowOutgoingRequests,
        ]);
    }
}

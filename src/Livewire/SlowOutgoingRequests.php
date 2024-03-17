<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Recorders\SlowOutgoingRequests as SlowOutgoingRequestsRecorder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * @internal
 */
#[Lazy]
class SlowOutgoingRequests extends Card
{
    use Concerns\HasThreshold;

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

                return (object) [
                    'method' => $method,
                    'uri' => $uri,
                    'slowest' => $row->max,
                    'count' => $row->count,
                ];
            }),
            $this->orderBy,
        );

        return View::make('pulse::livewire.slow-outgoing-requests', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.$this->recorder()),
            'slowOutgoingRequests' => $slowOutgoingRequests,
        ]);
    }

    /**
     * Get the recorder class.
     *
     * @return  class-string
     */
    protected function recorder(): string
    {
        return SlowOutgoingRequestsRecorder::class;
    }
}

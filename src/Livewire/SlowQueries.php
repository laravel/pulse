<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Recorders\SlowQueries as SlowQueriesRecorder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * @internal
 */
#[Lazy]
class SlowQueries extends Card
{
    use Concerns\HasThreshold;

    /**
     * Ordering.
     *
     * @var 'slowest'|'count'
     */
    #[Url(as: 'slow-queries')]
    public string $orderBy = 'slowest';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$slowQueries, $time, $runAt] = $this->remember(
            fn () => $this->aggregate(
                'slow_query',
                ['max', 'count'],
                match ($this->orderBy) {
                    'count' => 'count',
                    default => 'max',
                },
            )->map(function ($row) {
                [$sql, $location] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                return (object) [
                    'sql' => $sql,
                    'location' => $location,
                    'slowest' => $row->max,
                    'count' => $row->count,
                ];
            }),
            $this->orderBy,
        );

        return View::make('pulse::livewire.slow-queries', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => [
                // TODO remove fallback when tagging v1
                'highlighting' => true,
                ...Config::get('pulse.recorders.'.$this->recorder()),
            ],
            'slowQueries' => $slowQueries,
        ]);
    }

    /**
     * Get the recorder class.
     *
     * @return  class-string
     */
    protected function recorder(): string
    {
        return SlowQueriesRecorder::class;
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowQueries as SlowQueriesRecorder;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowQueries extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$slowQueries, $time, $runAt] = $this->remember(
            fn () => Pulse::aggregate('slow_query', ['max', 'count'], $this->periodAsInterval())
                ->map(function ($row) {
                    [$sql, $location] = json_decode($row->key);

                    return (object) [
                        'sql' => $sql,
                        'location' => $location,
                        'slowest' => $row->max,
                        'count' => $row->count,
                    ];
                })
        );

        return View::make('pulse::livewire.slow-queries', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.SlowQueriesRecorder::class),
            'slowQueries' => $slowQueries,
        ]);
    }
}

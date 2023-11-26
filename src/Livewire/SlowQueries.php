<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Queries\SlowQueries as SlowQueriesQuery;
use Laravel\Pulse\Recorders\SlowQueries as SlowQueriesRecorder;
use Livewire\Attributes\Lazy;

#[Lazy]
class SlowQueries extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(SlowQueriesQuery $query): Renderable
    {
        // [$slowQueries, $time, $runAt] = $this->remember($query);

        [$slowQueries, $time, $runAt] = $this->remember(fn () => Pulse::max('slow_query', $this->periodAsInterval())->map(function ($row) {
            [$sql, $location] = Str::contains($row->key, '::')
                ? [Str::beforeLast($row->key, '::'), Str::afterLast($row->key, '::')]
                : [$row->key, null];

            return (object) [
                'sql' => $sql,
                'location' => $location,
                'slowest' => $row->max,
                'count' => $row->count,
            ];
        }));

        return View::make('pulse::livewire.slow-queries', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.SlowQueriesRecorder::class),
            'slowQueries' => $slowQueries,
        ]);
    }
}

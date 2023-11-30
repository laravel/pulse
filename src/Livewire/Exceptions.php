<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Exceptions as ExceptionsRecorder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class Exceptions extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Ordering.
     *
     * @var 'count'|'latest'
     */
    #[Url(as: 'exceptions')]
    public string $orderBy = 'count';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$exceptions, $time, $runAt] = $this->remember(
            fn () => Pulse::aggregate(
                'exception',
                ['max', 'count'],
                $this->periodAsInterval(),
                match ($this->orderBy) {
                    'latest' => 'max',
                    default => 'count'
                },
            )->map(function ($row) {
                [$class, $location] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                return (object) [
                    'class' => $class,
                    'location' => $location,
                    'latest' => $row->max,
                    'count' => $row->count,
                ];
            }),
            $this->orderBy
        );

        return View::make('pulse::livewire.exceptions', [
            'time' => $time,
            'runAt' => $runAt,
            'exceptions' => $exceptions,
            'config' => Config::get('pulse.recorders.'.ExceptionsRecorder::class),
        ]);
    }
}

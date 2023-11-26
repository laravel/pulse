<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Exceptions as ExceptionsRecorder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class Exceptions extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * The view type
     *
     * @var 'count'|'latest'
     */
    #[Url(as: 'exceptions_by')]
    public string $orderBy = 'count';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$exceptions, $time, $runAt] = $this->remember(
            fn () => Pulse::max(
                'exception',
                $this->periodAsInterval(),
                match ($this->orderBy) {
                    'latest' => 'max',
                    default => 'count'
                },
            )->map(function ($row) {
                [$class, $location] = Str::contains($row->key, '::')
                    ? [Str::beforeLast($row->key, '::'), Str::afterLast($row->key, '::')]
                    : [$row->key, null];

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

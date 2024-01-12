<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Recorders\Exceptions as ExceptionsRecorder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * @internal
 */
#[Lazy]
class Exceptions extends Card
{
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
            fn () => $this->aggregate(
                'exception',
                ['max', 'count'],
                match ($this->orderBy) {
                    'latest' => 'max',
                    default => 'count'
                },
            )->map(function ($row) {
                [$class, $location] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                return (object) [
                    'class' => $class,
                    'location' => $location,
                    'latest' => CarbonImmutable::createFromTimestamp($row->max),
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

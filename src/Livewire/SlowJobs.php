<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowJobs as SlowJobsRecorder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class SlowJobs extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Ordering.
     *
     * @var 'slowest'|'count'
     */
    #[Url(as: 'slow-jobs')]
    public string $orderBy = 'slowest';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$slowJobs, $time, $runAt] = $this->remember(
            fn () => Pulse::aggregate(
                'slow_job',
                ['max', 'count'],
                $this->periodAsInterval(),
                match ($this->orderBy) {
                    'count' => 'count',
                    default => 'max',
                },
            )->map(fn ($row) => (object) [
                'job' => $row->key,
                'slowest' => $row->max,
                'count' => $row->count,
            ]),
            $this->orderBy,
        );

        return View::make('pulse::livewire.slow-jobs', [
            'time' => $time,
            'runAt' => $runAt,
            'config' => Config::get('pulse.recorders.'.SlowJobsRecorder::class),
            'slowJobs' => $slowJobs,
        ]);
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Recorders\Concerns\Thresholds;
use Laravel\Pulse\Recorders\SlowJobs as SlowJobsRecorder;
use Laravel\Pulse\Structs\SlowJobStruct;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * @internal
 */
#[Lazy]
class SlowJobs extends Card
{
    use Thresholds;

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
            fn () => $this->aggregate(
                'slow_job',
                ['max', 'count'],
                match ($this->orderBy) {
                    'count' => 'count',
                    default => 'max',
                },
            )->map(fn ($row) => new SlowJobStruct(
                job: $row->key,
                slowest: $row->max,
                count: $row->count,
                threshold: $this->threshold($row->key, SlowJobsRecorder::class),
            )),
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

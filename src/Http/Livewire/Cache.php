<?php

namespace Laravel\Pulse\Http\Livewire;

use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\ShouldNotReportUsage;
use Laravel\Pulse\Http\Livewire\Concerns\HasPeriod;
use Livewire\Component;

class Cache extends Component implements ShouldNotReportUsage
{
    use HasPeriod;

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        if (request()->hasHeader('X-Livewire')) {
            $this->loadData();
        }

        [$cacheInteractions, $time, $runAt] = $this->cacheInteractions();

        return view('pulse::livewire.cache', [
            'time' => $time,
            'runAt' => $runAt,
            'cacheInteractions' => $cacheInteractions,
            'initialDataLoaded' => $cacheInteractions !== null
        ]);
    }

    /**
     * The exceptions.
     *
     * @return array
     */
    protected function cacheInteractions()
    {
        return CacheFacade::get("pulse:cache:{$this->period}") ?? [null, 0, null];
    }

    /**
     * Load the data for the component.
     *
     * @return void
     */
    public function loadData()
    {
        CacheFacade::remember("pulse:cache:{$this->period}", now()->addSeconds(match ($this->period) {
            '6_hours' => 30,
            '24_hours' => 60,
            '7_days' => 600,
            default => 5,
        }), function () {
            $now = now()->toImmutable();

            $start = hrtime(true);

            $cacheInteractions = DB::table('pulse_cache_hits')
                ->selectRaw('COUNT(*) AS count, SUM(CASE WHEN `hit` = TRUE THEN 1 ELSE 0 END) as hits')
                ->where('date', '>=', $now->subHours(match ($this->period) {
                    '6_hours' => 6,
                    '24_hours' => 24,
                    '7_days' => 168,
                    default => 1,
                })->toDateTimeString())
                ->first();

            $cacheInteractions->hits ??= 0;

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$cacheInteractions, $time, $now->toDateTimeString()];
        });

        $this->dispatchBrowserEvent('cache:dataLoaded');
    }
}

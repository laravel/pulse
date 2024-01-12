<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserJobs;
use Laravel\Pulse\Recorders\UserRequests;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

/**
 * @internal
 */
#[Lazy]
class Usage extends Card
{
    /**
     * The type of usage to show.
     *
     * @var 'requests'|'slow_requests'|'jobs'|null
     */
    public ?string $type = null;

    /**
     * The usage type.
     *
     * @var 'requests'|'slow_requests'|'jobs'
     */
    #[Url]
    public string $usage = 'requests';

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        $type = $this->type ?? $this->usage;

        [$userRequestCounts, $time, $runAt] = $this->remember(
            function () use ($type) {
                $counts = $this->aggregate(
                    match ($type) {
                        'requests' => 'user_request',
                        'slow_requests' => 'slow_user_request',
                        'jobs' => 'user_job',
                    },
                    'count',
                    limit: 10,
                );

                $users = Pulse::resolveUsers($counts->pluck('key'));

                return $counts->map(fn ($row) => (object) [
                    'key' => $row->key,
                    'user' => $users->find($row->key),
                    'count' => (int) $row->count,
                ]);
            },
            $type
        );

        return View::make('pulse::livewire.usage', [
            'time' => $time,
            'runAt' => $runAt,
            'userRequestsConfig' => Config::get('pulse.recorders.'.UserRequests::class),
            'slowRequestsConfig' => Config::get('pulse.recorders.'.SlowRequests::class),
            'jobsConfig' => Config::get('pulse.recorders.'.UserJobs::class),
            'userRequestCounts' => $userRequestCounts,
        ]);
    }
}

<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Jobs;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserRequests;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class Usage extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

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
                $counts = app(Storage::class)->sum(
                    match ($type) {
                        'requests' => 'user_request',
                        'slow_requests' => 'slow_user_request',
                        'jobs' => 'user_job',
                    },
                    $this->periodAsInterval(),
                    limit: 10,
                );

                $users = app(Pulse::class)->resolveUsers($counts->pluck('key'));

                return $counts->map(function ($row) use ($users) {
                    $user = $users->firstWhere('id', $row->key);

                    return (object) [
                        'user' => (object) [
                            'id' => $row->key,
                            'name' => $user['name'] ?? 'Unknown',
                            'extra' => $user['extra'] ?? $user['email'] ?? '',
                            'avatar' => $user['avatar'] ?? (($user['email'] ?? false) ? "https://unavatar.io/{$user['email']}?fallback=".rawurlencode("https://source.boringavatars.com/marble/120/{$user['email']}?colors=2f2bad,ad2bad,e42692,f71568,f7db15") : null),
                        ],
                        'count' => (int) $row->sum,
                    ];
                });
            },
            $type
        );

        return View::make('pulse::livewire.usage', [
            'time' => $time,
            'runAt' => $runAt,
            'userRequestsConfig' => Config::get('pulse.recorders.'.UserRequests::class),
            'slowRequestsConfig' => Config::get('pulse.recorders.'.SlowRequests::class),
            'jobsConfig' => Config::get('pulse.recorders.'.Jobs::class),
            'userRequestCounts' => $userRequestCounts,
        ]);
    }
}

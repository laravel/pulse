<?php

namespace Laravel\Pulse\Packages\Deployments;

use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;

class Deployments extends Component
{
    use HasPeriod;
    use ShouldNotReportUsage;

    /**
     * @param  (callable(): Collection<int, array{id: int, name: string, email: string, avatar_url?: string})  $query
     */
    public function render(callable $query)
    {
        $deployments = $query($this->periodAsInterval(), 'avatar.io');

        return view('namespace::deployments', [
            'deployments' => $deployments,
        ]);
    }
}

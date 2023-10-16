<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Queries\Exceptions as ExceptionsQuery;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class Exceptions extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * The view type
     *
     * @var 'count'|'last_occurrence'
     */
    #[Url(as: 'exceptions_by')]
    public string $orderBy = 'count';

    /**
     * Render the component.
     */
    public function render(ExceptionsQuery $query): Renderable
    {
        $orderBy = match ($this->orderBy) {
            'last_occurrence' => 'last_occurrence',
            default => 'count'
        };

        [$exceptions, $time, $runAt] = $this->remember(fn ($interval) => $query($interval, $orderBy), $orderBy);

        return View::make('pulse::livewire.exceptions', [
            'time' => $time,
            'runAt' => $runAt,
            'exceptions' => $exceptions,
        ]);
    }
}

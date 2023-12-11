<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Attributes\Lazy;
use Livewire\Livewire;

/**
 * @internal
 */
#[Lazy]
class Servers extends Card
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        [$servers, $time, $runAt] = $this->remember(function () {
            $graphs = Pulse::graph(['cpu', 'memory'], 'avg', $this->periodAsInterval());

            return Pulse::values('system')
                ->map(function ($system, $slug) use ($graphs) {
                    $values = json_decode($system->value, flags: JSON_THROW_ON_ERROR);

                    return (object) [
                        'name' => (string) $values->name,
                        'cpu_current' => (int) $values->cpu,
                        'cpu' => $graphs->get($slug)?->get('cpu') ?? collect(),
                        'memory_current' => (int) $values->memory_used,
                        'memory_total' => (int) $values->memory_total,
                        'memory' => $graphs->get($slug)?->get('memory') ?? collect(),
                        'storage' => collect($values->storage), // @phpstan-ignore argument.templateType argument.templateType
                        'updated_at' => $updatedAt = CarbonImmutable::createFromTimestamp($system->timestamp),
                        'recently_reported' => $updatedAt->isAfter(now()->subSeconds(30)),
                    ];
                });
        });

        if (Livewire::isLivewireRequest()) {
            $this->dispatch('servers-chart-update', servers: $servers);
        }

        return View::make('pulse::livewire.servers', [
            'servers' => $servers,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.servers-placeholder', ['cols' => $this->cols, 'rows' => $this->rows, 'class' => $this->class]);
    }
}

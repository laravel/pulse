<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Component;
use Livewire\Livewire;

abstract class Card extends Component
{
    use Concerns\HasPeriod, Concerns\RemembersQueries;

    /**
     * The number of columns to span.
     *
     * @var 1|2|3|4|5|6|7|8|9|10|11|12|'full'
     */
    public int|string|null $cols = null;

    /**
     * The number of rows to span.
     *
     * @var 1|2|3|4|5|6|'full'
     */
    public int|string|null $rows = null;

    /**
     * Whether to expand the card body instead of scrolling.
     */
    public bool $expand = false;

    /**
     * Custom CSS classes.
     */
    public string $class = '';

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return View::make('pulse::components.placeholder', [
            'cols' => $this->cols ?? null,
            'rows' => $this->rows ?? null,
            'class' => $this->class,
        ]);
    }

    /**
     * Capture component-specific CSS.
     *
     * @return void
     */
    public function dehydrate()
    {
        if (Livewire::isLivewireRequest()) {
            return;
        }

        Pulse::css($this->css());
    }

    /**
     * Define any CSS that should be loaded for the component.
     *
     * @return string|\Illuminate\Contracts\Support\Htmlable|array<int, string|\Illuminate\Contracts\Support\Htmlable>|null
     */
    protected function css()
    {
        return null;
    }

    /**
     * Retrieve values for the given type.
     *
     * @param  list<string>  $keys
     * @return \Illuminate\Support\Collection<string, object{
     *     timestamp: int,
     *     key: string,
     *     value: string
     * }>
     */
    protected function values(string $type, ?array $keys = null): Collection
    {
        return Pulse::values($type, $keys);
    }

    /**
     * Retrieve aggregate values for plotting on a graph.
     *
     * @param  list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, int|null>>>
     */
    protected function graph(array $types, string $aggregate): Collection
    {
        return Pulse::graph($types, $aggregate, $this->periodAsInterval());
    }

    /**
     * Retrieve aggregate values for the given type.
     *
     * @param  'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function aggregate(
        string $type,
        string|array $aggregates,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        return Pulse::aggregate($type, $aggregates, $this->periodAsInterval(), $orderBy, $direction, $limit);
    }

    /**
     * Retrieve aggregate values for the given types.
     *
     * @param  string|list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function aggregateTypes(
        string|array $types,
        string $aggregate,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        return Pulse::aggregateTypes($types, $aggregate, $this->periodAsInterval(), $orderBy, $direction, $limit);
    }

    /**
     * Retrieve an aggregate total for the given types.
     *
     * @param  string|list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return float|\Illuminate\Support\Collection<string, int>
     */
    protected function aggregateTotal(
        array|string $types,
        string $aggregate,
    ): float|Collection {
        return Pulse::aggregateTotal($types, $aggregate, $this->periodAsInterval());
    }
}

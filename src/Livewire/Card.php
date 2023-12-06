<?php

namespace Laravel\Pulse\Livewire;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Component;
use Livewire\Livewire;

abstract class Card extends Component
{
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
}

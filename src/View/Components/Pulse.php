<?php

namespace Laravel\Pulse\View\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\View\Component;
use Illuminate\View\Factory;

class Pulse extends Component
{
    public function __construct(protected Factory $view)
    {
        //
    }

    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        return $this->view->make('pulse::components.pulse');
    }
}

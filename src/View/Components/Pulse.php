<?php

namespace Laravel\Pulse\View\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\View\Component;

class Pulse extends Component
{
    /**
     * Render the component.
     */
    public function render(): Renderable
    {
        return view('pulse::components.pulse');
    }
}

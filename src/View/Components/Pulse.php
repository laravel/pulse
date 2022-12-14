<?php

namespace Laravel\Pulse\View\Components;

use Illuminate\View\Component;

class Pulse extends Component
{
    public function render()
    {
        return view('pulse::components.pulse');
    }
}

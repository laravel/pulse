<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Livewire\SlowQueries;
use Livewire\Livewire;

it('always returns the run at date in UTC time', function () {
    date_default_timezone_set('Australia/Melbourne');
    Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', '2000-01-01 13:00:00', 'UTC'));

    Livewire::test(SlowQueries::class, ['lazy' => false, 'disableHighlighting' => true])
        ->assertSeeHtml(<<<'HTML'
            Run at: ${formatDate(&#039;2000-01-01 13:00:00&#039;)}
            HTML);
});

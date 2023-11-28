<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Ingests\Storage;
use Laravel\Pulse\Livewire\SlowJobs;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowJobs::class);
});

it('renders slow jobs', function () {
    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('slow_job', 'App\Jobs\MyJob', 1)->max();
    Pulse::record('slow_job', 'App\Jobs\MyOtherJob', 1)->max();

    // Add entries to the "tail".
    Carbon::setTestNow('2000-01-01 12:00:01');
    Pulse::record('slow_job', 'App\Jobs\MyJob', 1234)->max();
    Pulse::record('slow_job', 'App\Jobs\MyJob', 2468)->max();
    Pulse::record('slow_job', 'App\Jobs\MyOtherJob', 1234)->max();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('slow_job', 'App\Jobs\MyJob', 1000)->max();
    Pulse::record('slow_job', 'App\Jobs\MyJob', 1000)->max();
    Pulse::record('slow_job', 'App\Jobs\MyOtherJob', 1000)->max();

    Pulse::store(app(Storage::class));

    Livewire::test(SlowJobs::class, ['lazy' => false])
        ->assertViewHas('slowJobs', collect([
            (object) ['job' => 'App\Jobs\MyJob', 'count' => 4, 'slowest' => 2468],
            (object) ['job' => 'App\Jobs\MyOtherJob', 'count' => 2, 'slowest' => 1234],
        ]));
});

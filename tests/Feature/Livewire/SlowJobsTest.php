<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowJobs;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowJobs::class);
});

it('renders slow jobs', function () {
    Pulse::ignore(fn () => DB::table('pulse_jobs')->insert([
        ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\MyJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05', 'duration' => 1234, 'slow' => true],
        ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\MyJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05', 'duration' => 2468, 'slow' => true],
        ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\MyOtherJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05', 'duration' => 1234, 'slow' => true],
        ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\AnotherJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05', 'duration' => 900, 'slow' => false],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(SlowJobs::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('slowJobs', collect([
            (object) ['job' => 'App\Jobs\MyJob', 'count' => 2, 'slowest' => 2468],
            (object) ['job' => 'App\Jobs\MyOtherJob', 'count' => 1, 'slowest' => 1234],
        ]));
});

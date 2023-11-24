<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\SlowJobs;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(SlowJobs::class);
});

it('renders slow jobs', function () {
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_entries')->insert([
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_job', 'key' => 'App\Jobs\MyJob', 'value' => 1234],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_job', 'key' => 'App\Jobs\MyJob', 'value' => 2468],
        ['timestamp' => $timestamp - 3600 + 1, 'type' => 'slow_job', 'key' => 'App\Jobs\MyOtherJob', 'value' => 1234],
    ]));
    $currentBucket = (int) floor($timestamp / 60) * 60;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_job:count', 'key' => 'App\Jobs\MyJob', 'value' => 2],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_job:count', 'key' => 'App\Jobs\MyOtherJob', 'value' => 1],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_job:max', 'key' => 'App\Jobs\MyJob', 'value' => 1000],
        ['bucket' => $currentBucket, 'period' => 60, 'type' => 'slow_job:max', 'key' => 'App\Jobs\MyOtherJob', 'value' => 1000],
    ]));

    Livewire::test(SlowJobs::class, ['lazy' => false])
        ->assertViewHas('slowJobs', collect([
            (object) ['job' => 'App\Jobs\MyJob', 'count' => 4, 'slowest' => 2468],
            (object) ['job' => 'App\Jobs\MyOtherJob', 'count' => 2, 'slowest' => 1234],
        ]));
});

<?php

use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Queues;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Queues::class);
});

it('renders queue statistics', function () {
    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('queued', 'database:default')->count()->bucketOnly();
    Pulse::record('processing', 'database:default')->count()->bucketOnly();
    Pulse::record('processed', 'database:default')->count()->bucketOnly();
    Pulse::record('released', 'database:default')->count()->bucketOnly();
    Pulse::record('failed', 'database:default')->count()->bucketOnly();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('queued', 'database:default')->count()->bucketOnly();
    Pulse::record('queued', 'database:default')->count()->bucketOnly();
    Pulse::record('queued', 'database:default')->count()->bucketOnly();
    Pulse::record('queued', 'database:default')->count()->bucketOnly();
    Pulse::record('processing', 'database:default')->count()->bucketOnly();
    Pulse::record('processing', 'database:default')->count()->bucketOnly();
    Pulse::record('processing', 'database:default')->count()->bucketOnly();
    Pulse::record('processed', 'database:default')->count()->bucketOnly();
    Pulse::record('processed', 'database:default')->count()->bucketOnly();
    Pulse::record('released', 'database:default')->count()->bucketOnly();

    Pulse::store();

    Livewire::test(Queues::class, ['lazy' => false])
        ->assertViewHas('queues', collect([
            'database:default' => collect([
                'queued' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->toDateTimeString(), 4),
                'processing' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->toDateTimeString(), 3),
                'processed' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->toDateTimeString(), 2),
                'released' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->toDateTimeString(), 1),
                'failed' => collect()->range(59, 0)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null]),
            ]),
        ]))
        ->assertViewHas('showConnection', false);
});

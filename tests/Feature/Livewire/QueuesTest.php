<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Queues;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(Queues::class);
});

it('renders queue statistics', function () {
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'queued:count', 'key' => 'database:default', 'value' => 4],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'processing:count', 'key' => 'database:default', 'value' => 3],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'processed:count', 'key' => 'database:default', 'value' => 2],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'released:count', 'key' => 'database:default', 'value' => 1],
    ]));

    Livewire::test(Queues::class, ['lazy' => false])
        ->assertViewHas('queues', collect([
            'database:default' => collect()->range(59, 1)
                ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => [
                    'queued' => null,
                    'processing' => null,
                    'processed' => null,
                    'released' => null,
                    'failed' => null,
                ]])
                ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), [
                    'queued' => 4,
                    'processing' => 3,
                    'processed' => 2,
                    'released' => 1,
                    'failed' => 0,
                ]),
        ]),
        )
        ->assertViewHas('showConnection', false);
});

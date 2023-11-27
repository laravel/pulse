<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Queues;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Queues::class);
});

it('renders queue statistics', function () {
    Carbon::setTestNow(now()->setSeconds(30));
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'queued', 'aggregate' => 'sum', 'key' => 'database:default', 'value' => 4, 'count' => 4],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'processing', 'aggregate' => 'sum', 'key' => 'database:default', 'value' => 3, 'count' => 3],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'processed', 'aggregate' => 'sum', 'key' => 'database:default', 'value' => 2, 'count' => 2],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'released', 'aggregate' => 'sum', 'key' => 'database:default', 'value' => 1, 'count' => 1],
    ]));

    Livewire::test(Queues::class, ['lazy' => false])
        ->assertViewHas('queues', collect([
            'database:default' => collect([
                'queued' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), 4),
                'processing' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), 3),
                'processed' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), 2),
                'released' => collect()
                    ->range(59, 1)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), 1),
                'failed' => collect()->range(59, 0)->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null]),
            ]),
        ]))
        ->assertViewHas('showConnection', false);
});

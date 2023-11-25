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
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'queued:sum', 'key' => 'database:default', 'value' => 4, 'count' => 4],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'processing:sum', 'key' => 'database:default', 'value' => 3, 'count' => 3],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'processed:sum', 'key' => 'database:default', 'value' => 2, 'count' => 2],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'released:sum', 'key' => 'database:default', 'value' => 1, 'count' => 1],
    ]));

    Livewire::test(Queues::class, ['lazy' => false])
        ->assertViewHas('queues', collect([
            'database:default' => collect()->range(59, 1)
                ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => (object) [
                    'queued' => null,
                    'processing' => null,
                    'processed' => null,
                    'released' => null,
                    'failed' => null,
                ]])
                ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), (object) [
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

<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Servers;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    Pulse::authorizeUsing(fn () => true);

    $this
        ->get('/pulse')
        ->assertSeeLivewire(Servers::class);
});

it('renders server statistics', function () {
    $timestamp = now()->timestamp;
    Pulse::ignore(fn () => DB::table('pulse_aggregates')->insert([
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'cpu:avg', 'key' => 'web-1', 'value' => 12],
        ['bucket' => (int) floor($timestamp / 60) * 60, 'period' => 60, 'type' => 'memory:avg', 'key' => 'web-1', 'value' => 1234],
    ]));
    Pulse::ignore(fn () => DB::table('pulse_values')->insert([
        'key' => 'system:web-1',
        'value' => json_encode([
            'name' => 'Web 1',
            'timestamp' => $timestamp,
            'memory_used' => 1234,
            'memory_total' => 2468,
            'cpu' => 12,
            'storage' => [
                ['directory' => '/', 'used' => 123, 'total' => 456],
            ],
        ]),
    ]));

    Livewire::test(Servers::class, ['lazy' => false])
        ->assertViewHas('servers', collect([
            'web-1' => (object) [
                'name' => 'Web 1',
                'cpu_current' => 12,
                'memory_current' => 1234,
                'memory_total' => 2468,
                'storage' => collect([
                    (object) ['directory' => '/', 'used' => 123, 'total' => 456],
                ]),
                'cpu' => collect()->range(59, 1)
                    ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), 12),
                'memory' => collect()->range(59, 1)
                    ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp($timestamp)->startOfMinute()->toDateTimeString(), 1234),
                'updated_at' => CarbonImmutable::createFromTimestamp($timestamp),
                'recently_reported' => true,
            ],
        ]));
});

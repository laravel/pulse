<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Ingests\Storage;
use Laravel\Pulse\Livewire\Servers;
use Livewire\Livewire;

it('includes the card on the dashboard', function () {
    $this
        ->get('/pulse')
        ->assertSeeLivewire(Servers::class);
});

it('renders server statistics', function () {
    // Add entries outside of the window.
    Carbon::setTestNow('2000-01-01 12:00:00');
    Pulse::record('cpu', 'web-1', 1)->avg()->bucketOnly();
    Pulse::record('memory', 'web-1', 1)->avg()->bucketOnly();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('cpu', 'web-1', 25)->avg()->bucketOnly();
    Pulse::record('cpu', 'web-1', 50)->avg()->bucketOnly();
    Pulse::record('cpu', 'web-1', 75)->avg()->bucketOnly();
    Pulse::record('memory', 'web-1', 1000)->avg()->bucketOnly();
    Pulse::record('memory', 'web-1', 1500)->avg()->bucketOnly();
    Pulse::record('memory', 'web-1', 2000)->avg()->bucketOnly();
    Pulse::set('system', 'web-1', json_encode([
        'name' => 'Web 1',
        'memory_used' => 1234,
        'memory_total' => 2468,
        'cpu' => 99,
        'storage' => [
            ['directory' => '/', 'used' => 123, 'total' => 456],
        ],
    ]));

    Pulse::store(app(Storage::class));

    Livewire::test(Servers::class, ['lazy' => false])
        ->assertViewHas('servers', collect([
            'web-1' => (object) [
                'name' => 'Web 1',
                'cpu_current' => 99,
                'memory_current' => 1234,
                'memory_total' => 2468,
                'storage' => collect([
                    (object) ['directory' => '/', 'used' => 123, 'total' => 456],
                ]),
                'cpu' => collect()->range(59, 1)
                    ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->toDateTimeString(), 50),
                'memory' => collect()->range(59, 1)
                    ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->subMinutes($i)->toDateTimeString() => null])
                    ->put(Carbon::createFromTimestamp(now()->timestamp)->startOfMinute()->toDateTimeString(), 1500),
                'updated_at' => CarbonImmutable::createFromTimestamp(now()->timestamp),
                'recently_reported' => true,
            ],
        ]));
});

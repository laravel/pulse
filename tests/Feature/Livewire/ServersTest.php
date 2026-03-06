<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
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
    Pulse::record('cpu', 'web-1', 1)->avg()->onlyBuckets();
    Pulse::record('memory', 'web-1', 1)->avg()->onlyBuckets();

    // Add entries to the current buckets.
    Carbon::setTestNow('2000-01-01 13:00:00');
    Pulse::record('cpu', 'web-1', 25)->avg()->onlyBuckets();
    Pulse::record('cpu', 'web-1', 50)->avg()->onlyBuckets();
    Pulse::record('cpu', 'web-1', 75)->avg()->onlyBuckets();
    Pulse::record('memory', 'web-1', 1000)->avg()->onlyBuckets();
    Pulse::record('memory', 'web-1', 1500)->avg()->onlyBuckets();
    Pulse::record('memory', 'web-1', 2000)->avg()->onlyBuckets();
    Pulse::set('system', 'web-1', json_encode([
        'name' => 'Web 1',
        'memory_used' => 1234,
        'memory_total' => 2468,
        'cpu' => 99,
        'storage' => [
            ['directory' => '/', 'used' => 123, 'total' => 456],
        ],
    ]));

    Pulse::ingest();

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
                'booted_at' => null,
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

it('sorts by server name', function () {
    $data = [
        'memory_used' => 1234,
        'memory_total' => 2468,
        'cpu' => 99,
        'storage' => [
            ['directory' => '/', 'used' => 123, 'total' => 456],
        ],
    ];
    Pulse::set('system', 'b-web', json_encode([
        'name' => 'B Web',
        ...$data,
    ]));
    Pulse::set('system', 'a-web', json_encode([
        'name' => 'A Web',
        ...$data,
    ]));
    Pulse::set('system', 'c-web', json_encode([
        'name' => 'C Web',
        ...$data,
    ]));

    Pulse::ingest();

    Livewire::test(Servers::class, ['lazy' => false])
        ->assertSeeInOrder(['A Web', 'B Web', 'C Web']);
});

it('sorts descending', function () {
    $data = [
        'memory_used' => 1234,
        'memory_total' => 2468,
        'cpu' => 99,
        'storage' => [
            ['directory' => '/', 'used' => 123, 'total' => 456],
        ],
    ];
    Pulse::set('system', 'b-web', json_encode([
        'name' => 'B Web',
        ...$data,
    ]), now()->subSeconds(2));
    Pulse::set('system', 'a-web', json_encode([
        'name' => 'A Web',
        ...$data,
    ]), now()->subSeconds(3));
    Pulse::set('system', 'c-web', json_encode([
        'name' => 'C Web',
        ...$data,
    ]), now()->subSeconds(1));

    Pulse::ingest();

    Livewire::test(Servers::class, ['lazy' => false, 'sortDirection' => 'desc'])
        ->assertSeeInOrder(['C Web', 'B Web', 'A Web']);
});

it('sorts by updated at', function () {
    $data = [
        'memory_used' => 1234,
        'memory_total' => 2468,
        'cpu' => 99,
        'storage' => [
            ['directory' => '/', 'used' => 123, 'total' => 456],
        ],
    ];
    Pulse::set('system', 'b-web', json_encode([
        'name' => 'B Web',
        ...$data,
    ]), now()->subSeconds(2));
    Pulse::set('system', 'a-web', json_encode([
        'name' => 'A Web',
        ...$data,
    ]), now()->subSeconds(3));
    Pulse::set('system', 'c-web', json_encode([
        'name' => 'C Web',
        ...$data,
    ]), now()->subSeconds(1));

    Pulse::ingest();

    Livewire::test(Servers::class, ['lazy' => false, 'sortBy' => 'updated_at'])
        ->assertSeeInOrder(['A Web', 'B Web', 'C Web']);
});

it('can ignore servers that have stopped reporting', function ($ignoreAfter, $see, $dontSee) {
    Carbon::setTestNow(now()->startOfSecond());

    $data = [
        'memory_used' => 1234,
        'memory_total' => 2468,
        'cpu' => 99,
        'storage' => [
            ['directory' => '/', 'used' => 123, 'total' => 456],
        ],
    ];

    Pulse::set('system', 'server-1', json_encode([
        'name' => 'Server 1',
        ...$data,
    ]), now()->subSeconds(599));

    Pulse::set('system', 'server-2', json_encode([
        'name' => 'Server 2',
        ...$data,
    ]), now()->subSeconds(600));

    Pulse::set('system', 'server-3', json_encode([
        'name' => 'Server 3',
        ...$data,
    ]), now()->subSeconds(601));

    Pulse::ingest();

    Livewire::test(Servers::class, ['lazy' => false, 'ignoreAfter' => value($ignoreAfter)])
        ->assertSeeInOrder($see)
        ->assertDontSeeText($dontSee);
})->with([
    [null, ['Server 1', 'Server 2', 'Server 3'], []],
    [588, [], ['Server 1', 'Server 2', 'Server 3']],
    [599, ['Server 1'], ['Server 2', 'Server 3']],
    [600, ['Server 1', 'Server 2'], ['Server 3']],
    [601, ['Server 1', 'Server 2', 'Server 3'], []],
    ['588', [], ['Server 1', 'Server 2', 'Server 3']],
    ['599', ['Server 1'], ['Server 2', 'Server 3']],
    ['600', ['Server 1', 'Server 2'], ['Server 3']],
    ['601', ['Server 1', 'Server 2', 'Server 3'], []],
    ['588 seconds', [], ['Server 1', 'Server 2', 'Server 3']],
    ['599 seconds', ['Server 1'], ['Server 2', 'Server 3']],
    ['600 seconds', ['Server 1', 'Server 2'], ['Server 3']],
    ['601 seconds', ['Server 1', 'Server 2', 'Server 3'], []],
]);

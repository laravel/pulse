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
    Pulse::ignore(fn () => DB::table('pulse_system_stats')->insert([
        ['date' => '2000-01-02 03:04:00', 'server' => 'Web 1', 'cpu_percent' => 12, 'memory_used' => 1234, 'memory_total' => 2468, 'storage' => json_encode([['directory' => '/', 'used' => 123, 'total' => 456]])],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(Servers::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('servers', collect([
            'web-1' => (object) [
                'name' => 'Web 1',
                'slug' => 'web-1',
                'cpu_percent' => 12,
                'memory_used' => 1234,
                'memory_total' => 2468,
                'storage' => [
                    (object) ['directory' => '/', 'used' => 123, 'total' => 456],
                ],
                'readings' => [
                    (object) ['date' => '2000-01-02 02:05:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:06:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:07:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:08:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:09:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:10:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:11:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:12:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:13:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:14:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:15:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:16:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:17:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:18:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:19:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:20:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:21:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:22:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:23:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:24:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:25:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:26:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:27:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:28:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:29:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:30:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:31:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:32:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:33:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:34:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:35:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:36:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:37:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:38:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:39:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:40:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:41:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:42:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:43:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:44:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:45:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:46:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:47:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:48:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:49:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:50:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:51:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:52:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:53:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:54:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:55:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:56:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:57:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:58:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 02:59:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 03:00:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 03:01:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 03:02:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 03:03:00', 'cpu_percent' => null, 'memory_used' => null],
                    (object) ['date' => '2000-01-02 03:04:00', 'cpu_percent' => 12, 'memory_used' => 1234],
                ],
                'updated_at' => CarbonImmutable::parse('2000-01-02 03:04:00'),
                'recently_reported' => true,
            ],
        ]));
});

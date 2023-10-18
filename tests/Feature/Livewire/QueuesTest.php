<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
    Pulse::ignore(fn () => DB::table('pulse_jobs')->insert([
        ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\MyJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05'],
    ]));
    Carbon::setTestNow('2000-01-02 03:04:15');

    Livewire::test(Queues::class, ['lazy' => false])
        ->assertViewHas('time')
        ->assertViewHas('runAt', '2000-01-02 03:04:15')
        ->assertViewHas('queues', collect([
            'database:default' => collect([
                (object) ['date' => '2000-01-02 02:05', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:06', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:07', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:08', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:09', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:10', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:11', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:12', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:13', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:14', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:15', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:16', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:17', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:18', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:19', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:20', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:21', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:22', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:23', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:24', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:25', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:26', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:27', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:28', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:29', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:30', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:31', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:32', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:33', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:34', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:35', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:36', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:37', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:38', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:39', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:40', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:41', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:42', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:43', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:44', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:45', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:46', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:47', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:48', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:49', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:50', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:51', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:52', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:53', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:54', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:55', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:56', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:57', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:58', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 02:59', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 03:00', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 03:01', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 03:02', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 03:03', 'queued' => 0, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
                (object) ['date' => '2000-01-02 03:04', 'queued' => 1, 'processing' => 0, 'released' => 0, 'processed' => 0, 'failed' => 0],
            ]),
        ]))
        ->assertViewHas('showConnection', false)
        ->assertViewHas('config');
});

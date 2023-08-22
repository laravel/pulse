<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Pulse\Facades\Pulse;

it('ingests bus dispatched jobs', function () {
    Config::set('queue.default', 'database');
    Carbon::setTestNow('2000-01-02 03:04:05');

    Bus::dispatch(new MyJob);

    expect(Pulse::queue())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_jobs')->count())->toBe(0));

    Pulse::store();

    expect(Pulse::queue())->toHaveCount(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'processing_started_at' => null,
        'duration' => null,
        'job' => 'MyJob',
        'job_id' => '1',
    ]);
});

it('ingests queued closures', function () {
    Config::set('queue.default', 'database');
    Carbon::setTestNow('2000-01-02 03:04:05');

    dispatch(function () {
        //
    });
    Pulse::store();

    expect(Pulse::queue())->toHaveCount(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'processing_started_at' => null,
        'duration' => null,
        'job' => 'Illuminate\Queue\CallQueuedClosure',
        'job_id' => '1',
    ]);
});

it('ingests queue pushed jobs', function () {
    Config::set('queue.default', 'database');
    Carbon::setTestNow('2000-01-02 03:04:05');

    Queue::push('MyJob');
    Pulse::store();

    expect(Pulse::queue())->toHaveCount(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'processing_started_at' => null,
        'duration' => null,
        'job' => 'MyJob',
        'job_id' => '1',
    ]);
});

class MyJob implements ShouldQueue
{
    public function handle()
    {
        //
    }
}

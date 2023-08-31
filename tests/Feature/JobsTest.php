<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Facades\Pulse;

it('ingests bus dispatched jobs', function () {
    Config::set('queue.default', 'database');
    Carbon::setTestNow('2000-01-02 03:04:05');
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    Bus::dispatch(new MyJob);

    expect(Pulse::entries())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_jobs')->count())->toBe(0));

    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => null,
        'job' => 'MyJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
    ]);
});

it('ingests queued closures', function () {
    Config::set('queue.default', 'database');
    Carbon::setTestNow('2000-01-02 03:04:05');
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    dispatch(function () {
        //
    });
    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => null,
        'job' => 'Illuminate\Queue\CallQueuedClosure',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
    ]);
});

it('ingests jobs pushed to the queue', function () {
    Config::set('queue.default', 'database');
    Carbon::setTestNow('2000-01-02 03:04:05');
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    Queue::push('MyJob');
    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => null,
        'job' => 'MyJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
    ]);
});

it('updates the job when it finished processing', function () {
    Config::set('queue.default', 'database');
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);
    MyJob::$whenHandling = fn () => Carbon::setTestNow(now()->addMilliseconds(678));

    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatch(new MyJob);

    Carbon::setTestNow('2000-01-02 03:04:10.123');
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => 678,
        'job' => 'MyJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
    ]);
});

it('handles a job throwing exceptions and failing', function () {
    Config::set('queue.default', 'database');
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);
    $timeChanges = [
        fn () => Carbon::setTestNow(now()->addMilliseconds(123)),
        fn () => Carbon::setTestNow(now()->addMilliseconds(456)),
        fn () => Carbon::setTestNow(now()->addMilliseconds(789)),
    ];
    MyJobWithMultipleAttempts::$whenHandling = function () use (&$timeChanges) {
        array_shift($timeChanges)();
    };

    /*
     * Dispatch the job.
     */
    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatch(new MyJobWithMultipleAttempts);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(0);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10.123');
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => 123,
        'job' => 'MyJobWithMultipleAttempts',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
    ]);

    /*
     * Work the job for the second time.
     */

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => 456,
        'job' => 'MyJobWithMultipleAttempts',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
    ]);

    /*
     * Work the job for the third time.
     */

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'duration' => 789,
        'job' => 'MyJobWithMultipleAttempts',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
    ]);

    // we expect there to be a single exception in the queue. This would
    // be stored on the next loop or while the command terminates.
    expect(Pulse::entries())->toHaveCount(1);
    expect(Pulse::entries()[0])->toBeInstanceOf(Entry::class);
    expect(Pulse::entries()[0]->table)->toBe('pulse_exceptions');
    Pulse::flushEntries();
});

it('only remembers the longest duration')->todo();

it('handles a failure and then a successful job')->todo();

it('handles a job that was manually failed')->todo();

class MyJob implements ShouldQueue
{
    public static $whenHandling;

    public function handle()
    {
        if (static::$whenHandling) {
            (static::$whenHandling)();
        }
    }
}

class MyJobWithMultipleAttempts implements ShouldQueue
{
    public static $whenHandling;

    public $tries = 3;

    public function handle()
    {
        if (static::$whenHandling) {
            (static::$whenHandling)();
        }

        echo 'handling the job';
        throw new Exception('Job failed');
    }
}

<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
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

    Bus::dispatchToQueue(new MyJob);

    expect(Pulse::entries())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_jobs')->count())->toBe(0));

    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
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
        'job' => 'Illuminate\Queue\CallQueuedClosure',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
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
        'job' => 'MyJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
    ]);
})->only();

it('handles a job throwing exceptions and failing', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.slow_job_threshold', 0);
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    /*
     * Dispatch the job.
     */
    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatchToQueue(new MyJobWithMultipleAttemptsThatAlwaysThrows);
    Pulse::store(app(Ingest::class));

    expect(Queue::size())->toBe(1);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 1,
        'slowest' => 11,
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
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 2,
        'slowest' => 22,
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
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 3,
        'slowest' => 33,
    ]);

    // // we expect there to be a single exception in the queue. This would
    // // be stored on the next loop or while the command terminates.
    expect(Pulse::entries())->toHaveCount(1);
    expect(Pulse::entries()[0])->toBeInstanceOf(Entry::class);
    expect(Pulse::entries()[0]->table)->toBe('pulse_exceptions');
    Pulse::flushEntries();
})->only();

it('only remembers the slowest duration', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.slow_job_threshold', 0);
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    /*
     * Dispatch the job.
     */
    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatchToQueue(new MyJobWithMultipleAttemptsThatGetQuicker);
    Pulse::store(app(Ingest::class));

    expect(Queue::size())->toBe(1);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatGetQuicker',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatGetQuicker',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 1,
        'slowest' => 99,
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
        'job' => 'MyJobWithMultipleAttemptsThatGetQuicker',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 2,
        'slowest' => 99,
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
        'job' => 'MyJobWithMultipleAttemptsThatGetQuicker',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 3,
        'slowest' => 99,
    ]);

    // // we expect there to be a single exception in the queue. This would
    // // be stored on the next loop or while the command terminates.
    expect(Pulse::entries())->toHaveCount(1);
    expect(Pulse::entries()[0])->toBeInstanceOf(Entry::class);
    expect(Pulse::entries()[0]->table)->toBe('pulse_exceptions');
    Pulse::flushEntries();
})->only();

it('handles a failure and then a successful job', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.slow_job_threshold', 0);
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    /*
     * Dispatch the job.
     */
    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatchToQueue(new MyJobThatPassesOnTheSecondAttempt);
    Pulse::store(app(Ingest::class));

    expect(Queue::size())->toBe(1);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobThatPassesOnTheSecondAttempt',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobThatPassesOnTheSecondAttempt',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 1,
        'slowest' => 99,
    ]);

    /*
     * Work the job for the second time.
     */

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobThatPassesOnTheSecondAttempt',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 2,
        'slowest' => 99,
    ]);
})->only();

it('handles a slow successful job', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.slow_job_threshold', 0);
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    /*
     * Dispatch the job.
     */
    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatchToQueue(new MySlowJob);
    Pulse::store(app(Ingest::class));

    expect(Queue::size())->toBe(1);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MySlowJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MySlowJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 1,
        'slowest' => 100,
    ]);
})->only();

it('handles a job that was manually failed', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.slow_job_threshold', 0);
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    /*
     * Dispatch the job.
     */
    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatchToQueue(new MyJobThatManuallyFails);
    Pulse::store(app(Ingest::class));

    expect(Queue::size())->toBe(1);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobThatManuallyFails',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 0,
        'slowest' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect((array) $jobs[0])->toEqual([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'job' => 'MyJobThatManuallyFails',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'slow' => 1,
        'slowest' => 100,
    ]);

    // // we expect there to be a single exception in the queue. This would
    // // be stored on the next loop or while the command terminates.
    expect(Pulse::entries())->toHaveCount(1);
    expect(Pulse::entries()[0])->toBeInstanceOf(Entry::class);
    expect(Pulse::entries()[0]->table)->toBe('pulse_exceptions');
    Pulse::flushEntries();
})->todo();

class MyJob implements ShouldQueue
{
    public function handle()
    {
        //
    }
}

class MyJobWithMultipleAttemptsThatAlwaysThrows implements ShouldQueue
{
    public $tries = 3;

    public function handle()
    {
        static $attempts = 0;

        $attempts++;

        Carbon::setTestNow(Carbon::now()->addMilliseconds(11 * $attempts));

        throw new RuntimeException('Nope');
    }
}

class MyJobWithMultipleAttemptsThatGetQuicker implements ShouldQueue
{
    public $tries = 3;

    public function handle()
    {
        static $attempts = 0;

        $attempts++;

        Carbon::setTestNow(Carbon::now()->addMilliseconds(100 - $attempts));

        throw new RuntimeException('Nope');
    }
}

class MyJobThatPassesOnTheSecondAttempt implements ShouldQueue
{
    public $tries = 3;

    public function handle()
    {
        static $attempts = 0;

        $attempts++;

        Carbon::setTestNow(Carbon::now()->addMilliseconds(100 - $attempts));

        if ($attempts === 1) {
            throw new RuntimeException('Nope');
        }
    }
}

class MySlowJob implements ShouldQueue
{
    public function handle()
    {
        Carbon::setTestNow(Carbon::now()->addMilliseconds(100));
    }
}

class MyJobThatManuallyFails implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle()
    {
        Carbon::setTestNow(Carbon::now()->addMilliseconds(100));

        $this->fail();
    }
}

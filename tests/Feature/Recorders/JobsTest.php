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
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Jobs;

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
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
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
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'Illuminate\Queue\CallQueuedClosure',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
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
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);
});

it('handles a job throwing exceptions and failing', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.Jobs::class.'.threshold', 0);
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
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->orderBy('date')->get());
    expect($jobs)->toHaveCount(2);
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:10',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => '2000-01-02 03:04:10',
        'released_at' => '2000-01-02 03:04:10',
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 11,
    ]);
    expect($jobs[1])->toHaveProperties([
        'date' => '2000-01-02 03:04:10',
        'queued_at' => '2000-01-02 03:04:10',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 2,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);

    /*
     * Work the job for the second time.
     */

    Carbon::setTestNow('2000-01-02 03:04:15');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->orderBy('date')->get());
    expect($jobs)->toHaveCount(3);
    expect($jobs[1])->toHaveProperties([
        'date' => '2000-01-02 03:04:15',
        'queued_at' => '2000-01-02 03:04:10',
        'processing_at' => '2000-01-02 03:04:15',
        'released_at' => '2000-01-02 03:04:15',
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 2,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 22,
    ]);
    expect($jobs[2])->toHaveProperties([
        'date' => '2000-01-02 03:04:15',
        'queued_at' => '2000-01-02 03:04:15',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 3,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);

    /*
     * Work the job for the third time.
     */

    Carbon::setTestNow('2000-01-02 03:04:20');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->orderBy('date')->get());
    expect($jobs)->toHaveCount(3);
    expect($jobs[2])->toHaveProperties([
        'date' => '2000-01-02 03:04:20',
        'queued_at' => '2000-01-02 03:04:15',
        'processing_at' => '2000-01-02 03:04:20',
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => '2000-01-02 03:04:20',
        'user_id' => null,
        'job' => 'MyJobWithMultipleAttemptsThatAlwaysThrows',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 3,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 33,
    ]);
});

it('handles a failure and then a successful job', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.Jobs::class.'.threshold', 0);
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
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobThatPassesOnTheSecondAttempt',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(2);
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:10',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => '2000-01-02 03:04:10',
        'released_at' => '2000-01-02 03:04:10',
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobThatPassesOnTheSecondAttempt',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 99,
    ]);
    expect($jobs[1])->toHaveProperties([
        'date' => '2000-01-02 03:04:10',
        'queued_at' => '2000-01-02 03:04:10',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobThatPassesOnTheSecondAttempt',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 2,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);

    /*
     * Work the job for the second time.
     */

    Carbon::setTestNow('2000-01-02 03:04:15');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(2);
    expect($jobs[1])->toHaveProperties([
        'date' => '2000-01-02 03:04:15',
        'queued_at' => '2000-01-02 03:04:10',
        'processing_at' => '2000-01-02 03:04:15',
        'released_at' => null,
        'processed_at' => '2000-01-02 03:04:15',
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobThatPassesOnTheSecondAttempt',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 2,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 98,
    ]);
});

it('handles a slow successful job', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.Jobs::class.'.threshold', 0);
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
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MySlowJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:10',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => '2000-01-02 03:04:10',
        'released_at' => null,
        'processed_at' => '2000-01-02 03:04:10',
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MySlowJob',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 100,
    ]);
});

it('handles a job that was manually failed', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.Jobs::class.'.threshold', 0);
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
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => null,
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => null,
        'user_id' => null,
        'job' => 'MyJobThatManuallyFails',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => null,
    ]);

    /*
     * Work the job for the first time.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(1);
    expect($jobs[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:10',
        'queued_at' => '2000-01-02 03:04:05',
        'processing_at' => '2000-01-02 03:04:10',
        'released_at' => null,
        'processed_at' => null,
        'failed_at' => '2000-01-02 03:04:10',
        'user_id' => null,
        'job' => 'MyJobThatManuallyFails',
        'job_uuid' => 'e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd',
        'attempt' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'duration' => 100,
    ]);
});

it('can ignore jobs', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.Jobs::class.'.ignore', [
        '/My/',
    ]);
    MyJobThatPassesOnTheSecondAttempt::$attempts = 0;
    Bus::dispatchToQueue(new MyJobThatPassesOnTheSecondAttempt);
    expect(Queue::size())->toBe(1);
    expect(Pulse::entries())->toHaveCount(0);

    /*
     * Work the job for the first time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(1);
    expect(Pulse::ignore(fn () => DB::table('pulse_jobs')->count()))->toBe(0);

    /*
     * Work the job for the second time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);
    expect(Pulse::ignore(fn () => DB::table('pulse_jobs')->count()))->toBe(0);
});

it('can sample', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.Jobs::class.'.sample_rate', 0.1);

    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);
    Bus::dispatchToQueue(new MyJob);

    expect(Queue::size())->toBe(10);
    expect(count(Pulse::entries()))->toEqualWithDelta(1, 4);

    Pulse::flushEntries();
});

it("doesn't sample subsequent events for jobs that aren't initially sampled", function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.Jobs::class.'.sample_rate', 0.5);
    Str::createUuidsUsingSequence([
        '9a6569d9-ce2e-4e3a-924f-48e2de48a3b3', // Always sampled
        '9a656a13-c0b0-48e9-bc6e-bce99deb48f5', // Never sampled
    ]);

    Bus::dispatchToQueue(new MyJobThatAlwaysFails);
    Bus::dispatchToQueue(new MyJobThatAlwaysFails);
    Pulse::store(app(Ingest::class));

    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect(Queue::size())->toBe(2);
    expect($jobs)->toHaveCount(1);
    expect($jobs[0])->toHaveProperties([
        'job_uuid' => '9a6569d9-ce2e-4e3a-924f-48e2de48a3b3',
        'attempt' => 1,
    ]);

    Artisan::call('queue:work', ['--tries' => 2, '--max-jobs' => 4, '--stop-when-empty' => true]);
    expect(Queue::size())->toBe(0);
    $jobs = Pulse::ignore(fn () => DB::table('pulse_jobs')->get());
    expect($jobs)->toHaveCount(2);
    expect($jobs[0])->toHaveProperties([
        'job_uuid' => '9a6569d9-ce2e-4e3a-924f-48e2de48a3b3',
        'attempt' => 1,
    ]);
    expect($jobs[1])->toHaveProperties([
        'job_uuid' => '9a6569d9-ce2e-4e3a-924f-48e2de48a3b3',
        'attempt' => 2,
    ]);
});

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

    public static $attempts = 0;

    public function handle()
    {
        static::$attempts++;

        Carbon::setTestNow(Carbon::now()->addMilliseconds(100 - static::$attempts));

        if (static::$attempts === 1) {
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

class MyJobThatAlwaysFails implements ShouldQueue
{
    public function handle()
    {
        throw new RuntimeException('Nope');
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

<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowJobs;
use Orchestra\Testbench\Attributes\ResetRefreshDatabaseState;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;

use function Orchestra\Testbench\Pest\usesTestingFeature;

usesTestingFeature(
    new ResetRefreshDatabaseState(),
    new WithMigration('laravel', 'queue'),
    new WithConfig('queue.failed.driver', 'null'),
);

it('records slow jobs', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.SlowJobs::class.'.threshold', 100);
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    /*
     * Dispatch the job.
     */

    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatchToQueue(new MySlowJob);
    Pulse::ingest();

    Pulse::ignore(fn () => expect(Queue::size())->toBe(1));
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('type', 'slow_job')->get()))->toHaveCount(0);

    /*
     * Work the job.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    Pulse::ignore(fn () => expect(Queue::size())->toBe(0));
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->where('type', 'slow_job')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_job',
        'key' => MySlowJob::class,
        'value' => 100,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('type', 'slow_job')->orderBy('aggregate')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'slow_job',
        aggregate: 'count',
        key: MySlowJob::class,
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'slow_job',
        aggregate: 'max',
        key: MySlowJob::class,
        value: 100,
    );
});

it('skips jobs under the threshold', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.SlowJobs::class.'.threshold', 200);
    Str::createUuidsUsingSequence(['e2cb5fa7-6c2e-4bc5-82c9-45e79c3e8fdd']);

    /*
     * Dispatch the job.
     */

    Carbon::setTestNow('2000-01-02 03:04:05');
    Bus::dispatchToQueue(new MySlowJob);
    Pulse::ingest();

    Pulse::ignore(fn () => expect(Queue::size())->toBe(1));
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('type', 'slow_job')->get()))->toHaveCount(0);

    /*
     * Work the job.
     */

    Carbon::setTestNow('2000-01-02 03:04:10');
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    Pulse::ignore(fn () => expect(Queue::size())->toBe(0));
    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->where('type', 'slow_job')->get()))->toHaveCount(0);
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('type', 'slow_job')->get()))->toHaveCount(0);
});

it('can ignore jobs', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.SlowJobs::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowJobs::class.'.ignore', [
        '/My/',
    ]);

    /*
     * Dispatch the job.
     */

    Bus::dispatchToQueue(new MySlowJob);
    Pulse::ingest();

    Pulse::ignore(fn () => expect(Queue::size())->toBe(1));
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('type', 'slow_job')->get()))->toHaveCount(0);

    /*
     * Work the job.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);

    Pulse::ignore(fn () => expect(Queue::size())->toBe(0));
    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->where('type', 'slow_job')->get()))->toHaveCount(0);
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('type', 'slow_job')->get()))->toHaveCount(0);
});

it('can sample', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.SlowJobs::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowJobs::class.'.sample_rate', 0.1);

    /*
     * Dispatch the jobs.
     */

    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Pulse::ingest();

    Pulse::ignore(fn () => expect(Queue::size())->toBe(10));

    /*
     * Work the jobs.
     */

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);

    Pulse::ignore(fn () => expect(Queue::size())->toBe(0));
    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->where('type', 'slow_job')->count()))->toEqualWithDelta(1, 4);

    Pulse::flush();
});

it('can sample at zero', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.SlowJobs::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowJobs::class.'.sample_rate', 0);

    /*
     * Dispatch the jobs.
     */

    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Pulse::ingest();

    Pulse::ignore(fn () => expect(Queue::size())->toBe(10));

    /*
     * Work the jobs.
     */

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);

    Pulse::ignore(fn () => expect(Queue::size())->toBe(0));
    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->where('type', 'slow_job')->count()))->toBe(0);

    Pulse::flush();
});

it('can sample at one', function () {
    Config::set('queue.default', 'database');
    Config::set('pulse.recorders.'.SlowJobs::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowJobs::class.'.sample_rate', 1);

    /*
     * Dispatch the jobs.
     */

    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Bus::dispatchToQueue(new MySlowJob);
    Pulse::ingest();

    Pulse::ignore(fn () => expect(Queue::size())->toBe(10));

    /*
     * Work the jobs.
     */

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);

    Pulse::ignore(fn () => expect(Queue::size())->toBe(0));
    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->where('type', 'slow_job')->count()))->toBe(10);

    Pulse::flush();
});

class MySlowJob implements ShouldQueue
{
    public function handle()
    {
        Carbon::setTestNow(Carbon::now()->addMilliseconds(100));
    }
}

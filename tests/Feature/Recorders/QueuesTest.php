<?php

use Illuminate\Bus\Queueable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Queues;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function ($app) {
    $app['config']->set([
        'queue.default' => 'database'
    ]);
});

function queueAggregates()
{
    return Pulse::ignore(fn () => DB::table('pulse_aggregates')->whereIn('type', [
        'queued',
        'processing',
        'processed',
        'released',
        'failed',
        'slow_job',
    ])->get());
}

it('ingests bus dispatched jobs', function () {
    Bus::dispatchToQueue(new MyJob);

    Pulse::store();

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
});

it('ingests queued closures', function () {

    dispatch(function () {
        throw new RuntimeException('Nope');
    });

    Pulse::store();

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the second time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'released', 'failed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
});

it('ingests jobs pushed to the queue', function () {
    Queue::push('MyJob');
    Pulse::store();

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
});

it('ingests queued listeners', function () {
    Event::listen(MyEvent::class, MyListenerThatFails::class);

    /*
     * Dispatch the event.
     */
    MyEvent::dispatch();
    Pulse::store();

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the second time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'released', 'failed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
});

it('ingests queued mail', function () {

    /*
     * Dispatch the mail.
     */
    Mail::to('test@example.com')->queue(new MyMailThatFails);
    Pulse::store();

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the second time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'released', 'failed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
});

it('ingests queued notifications', function () {
    /*
     * Dispatch the notification.
     */
    Notification::route('mail', 'test@example.com')->notify(new MyNotificationThatFails);
    Pulse::store();

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the second time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'released', 'failed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
});

it('ingests queued commands', function () {
    /*
     * Dispatch the command.
     */
    Artisan::queue(MyCommandThatFails::class);
    Pulse::store();

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the second time.
     */
    Artisan::call('queue:work', ['--max-jobs' => 1, '--tries' => 2, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'released', 'failed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
});

it('handles a job throwing exceptions and failing', function () {
    /*
     * Dispatch the job.
     */
    Bus::dispatchToQueue(new MyJobWithMultipleAttemptsThatAlwaysThrows);
    Pulse::store();

    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the second time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );

    /*
     * Work the job for the third time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'failed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'released',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 3,
    );
});

it('handles a failure and then a successful job', function () {
    /*
     * Dispatch the job.
     */
    Bus::dispatchToQueue(new MyJobThatPassesOnTheSecondAttempt);
    Pulse::store();

    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(12);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'released'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the second time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'released', 'processed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
});

it('handles a job that was manually failed', function () {
    /*
     * Dispatch the job.
     */
    Bus::dispatchToQueue(new MyJobThatManuallyFails);
    Pulse::store();

    expect(Queue::size())->toBe(1);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    /*
     * Work the job for the first time.
     */

    app(ExceptionHandler::class)->reportable(fn (\Throwable $e) => throw $e);
    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    app()->forgetInstance(ExceptionHandler::class);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'processing', 'failed', 'processed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
});

it('can ignore jobs', function () {
    Config::set('pulse.recorders.'.Queues::class.'.ignore', [
        '/My/',
    ]);
    MyJobThatPassesOnTheSecondAttempt::$attempts = 0;
    Bus::dispatchToQueue(new MyJobThatPassesOnTheSecondAttempt);
    expect(Queue::size())->toBe(1);

    /*
     * Work the job for the first time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(1);
    expect(queueAggregates())->toHaveCount(0);

    /*
     * Work the job for the second time.
     */

    Artisan::call('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    expect(queueAggregates())->toHaveCount(0);
});

it('can sample', function () {
    Config::set('pulse.recorders.'.Queues::class.'.sample_rate', 0.1);

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
    Pulse::store();

    expect(Queue::size())->toBe(10);
    expect(queueAggregates()->count())->toEqualWithDelta(1, 4);
});

it('can sample at zero', function () {
    Config::set('pulse.recorders.'.Queues::class.'.sample_rate', 0);

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
    expect(Pulse::store())->toBe(0);
});

it('can sample at one', function () {
    Config::set('pulse.recorders.'.Queues::class.'.sample_rate', 1);

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
    expect(Pulse::store())->toBe(10);
});

it("doesn't sample subsequent events for jobs that aren't initially sampled", function () {
    Config::set('pulse.recorders.'.Queues::class.'.sample_rate', 0.5);
    Str::createUuidsUsingSequence([
        '9a6569d9-ce2e-4e3a-924f-48e2de48a3b3', // Always sampled
        '9a656a13-c0b0-48e9-bc6e-bce99deb48f5', // Never sampled
    ]);

    Bus::dispatchToQueue(new MyJobThatAlwaysFails);
    Bus::dispatchToQueue(new MyJobThatAlwaysFails);
    Pulse::store();

    expect(Queue::size())->toBe(2);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );

    Artisan::call('queue:work', ['--tries' => 2, '--max-jobs' => 4, '--stop-when-empty' => true, '--sleep' => 0]);
    expect(Queue::size())->toBe(0);
    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(16);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: ['queued', 'released', 'failed'],
        aggregate: 'count',
        key: 'database:default',
        value: 1,
    );
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'processing',
        aggregate: 'count',
        key: 'database:default',
        value: 2,
    );
});

it('uses the connection default queue when a job has no queue specified', function () {
    Config::set('queue.connections.database.queue', 'custom-default');

    Bus::dispatchToQueue(new MyJob);
    Pulse::store();
    expect(Queue::size())->toBe(1);

    $aggregates = queueAggregates();
    expect($aggregates)->toHaveCount(4);
    expect($aggregates)->toContainAggregateForAllPeriods(
        type: 'queued',
        aggregate: 'count',
        key: 'database:custom-default',
        value: 1,
    );
});

class MyJob implements ShouldQueue
{
    public function handle()
    {
        //
    }
}

class MyEvent
{
    use Dispatchable;
}

class MyListenerThatFails implements ShouldQueue
{
    public function handle()
    {
        throw new RuntimeException('Nope');
    }
}

class MyMailThatFails extends Mailable implements ShouldQueue
{
    public function content()
    {
        throw new RuntimeException('Nope');
    }
}

class MyNotificationThatFails extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['mail'];
    }

    public function toMail()
    {
        throw new RuntimeException('Nope');
    }
}

class MyCommandThatFails extends Command
{
    public function handle()
    {
        throw new RuntimeException('Nope');
    }
}

class MyJobWithMultipleAttemptsThatAlwaysThrows implements ShouldQueue
{
    public $tries = 3;

    public function handle()
    {
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

        if (static::$attempts === 1) {
            throw new RuntimeException('Nope');
        }
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
        $this->fail();
    }
}

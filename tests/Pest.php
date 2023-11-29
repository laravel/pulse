<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)
    ->beforeEach(function () {
        Model::unguard();
        Http::preventStrayRequests();
        Pulse::handleExceptionsUsing(fn (Throwable $e) => throw $e);
        Gate::define('viewPulse', fn ($user = null) => true);
    })
    ->afterEach(function () {
        if (Pulse::queue()->isNotEmpty()) {
            throw new RuntimeException('The queue is not empty');
        }
    })
    ->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toContainAggregateForAllPeriods', function (string|array $type, string $aggregate, string $key, int $value, int $count = null, int $timestamp = null) {
    $this->toBeInstanceOf(Collection::class);

    $types = (array) $type;
    $timestamp ??= now()->timestamp;

    $periods = collect([60, 360, 1440, 10080]);

    foreach ($types as $type) {
        foreach ($periods as $period) {
            $record = (object) [
                'bucket' => (int) (floor($timestamp / $period) * $period),
                'period' => $period,
                'type' => $type,
                'aggregate' => $aggregate,
                'key' => $key,
                'key_hash' => hex2bin(md5($key)),
                'value' => $value,
                'count' => $count,
            ];

            Assert::assertContainsEquals($record, $this->value);
        }
    }

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function prependListener(string $event, callable $listener): void
{
    $listeners = Event::getRawListeners()[$event];

    Event::forget($event);

    collect([$listener, ...$listeners])->each(fn ($listener) => Event::listen($event, $listener));
}

function captureRedisCommands(callable $callback)
{
    $port = config('database.redis.default.port');

    $process = Process::timeout(10)->start("redis-cli -p {$port} MONITOR");

    Sleep::for(50)->milliseconds();

    $beforeFlag = Str::random();
    Process::timeout(1)->run("redis-cli -p {$port} ping {$beforeFlag}")->throw();

    $pingedAt = CarbonImmutable::now();

    while (! str_contains($process->output(), $beforeFlag) && $pingedAt->addSeconds(3)->isFuture()) {
        Sleep::for(50)->milliseconds();
    }

    if (! str_contains($process->output(), $beforeFlag)) {
        throw new Exception('Redis before PING was never recorded.');
    }

    try {
        $callback();

        $afterFlag = Str::random();
        Process::timeout(1)->run("redis-cli -p {$port} ping {$afterFlag}")->throw();

        $pingedAt = CarbonImmutable::now();

        while (! str_contains($process->output(), $afterFlag) && $pingedAt->addSeconds(3)->isFuture()) {
            Sleep::for(50)->milliseconds();
        }

        if (! str_contains($process->output(), $afterFlag)) {
            throw new Exception('Redis after PING was never recorded.');
        }

        return collect(explode("\n", $process->signal(SIGINT)->output()))
            ->skipUntil(fn ($value) => str_contains($value, $beforeFlag))
            ->skip(1)
            ->filter(fn ($output) => $output && ! str_contains($output, $afterFlag))
            ->map(fn ($value) => Str::after($value, '] '))
            ->values();
    } finally {
        $process->running() && $process->signal(SIGINT);
    }
}

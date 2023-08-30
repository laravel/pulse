<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
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
        Pulse::flushEntries();
        Pulse::handleExceptionsUsing(fn (Throwable $e) => throw $e);
    })
    ->afterEach(function () {
        if (Pulse::entries()->isNotEmpty()) {
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

// ...

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
    $process = Process::timeout(30)->start('redis-cli MONITOR');

    Sleep::for(50)->milliseconds();

    $pingFlag = Str::random();
    Process::timeout(10)->run("redis-cli ping {$pingFlag}")->throw();

    $pingedAt = new CarbonImmutable;

    while (! str_contains($process->output(), $pingFlag) && $pingedAt->addSeconds(3)->isFuture()) {
        Sleep::for(50)->milliseconds();
    }

    if (! str_contains($process->output(), $pingFlag)) {
        throw new Exception('Redis PING was never recorded.');
    }

    try {
        $callback();

        return collect(explode("\n", $process->signal(SIGINT)->output()))
            ->skipUntil(fn ($value) => str_contains($value, $pingFlag))
            ->skip(1)
            ->filter()
            ->map(fn ($value) => Str::after($value, '] '))
            ->values();
    } finally {
        $process->signal(SIGINT);
    }
}

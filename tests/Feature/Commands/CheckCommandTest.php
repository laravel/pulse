<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Events\IsolatedBeat;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Recorders\Concerns\Throttling;

beforeEach(function () {
    Carbon::setTestNow(now()->startOfDay());
});

it('loops when not on vapor', function () {
    $called = 0;
    Sleep::fake();
    Sleep::whenFakingSleep(function () use (&$called) {
        if ($called > 5) {
            throw new RuntimeException('bail');
        }
    });
    Event::listen(function (SharedBeat $beat) use (&$called) {
        $called++;
    });

    try {
        Artisan::call('pulse:check');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'bail') {
            throw $e;
        }
    }

    expect($called)->toBe(6);
    Sleep::assertSequence([
        Sleep::for(1)->second(),
        Sleep::for(1)->second(),
        Sleep::for(1)->second(),
        Sleep::for(1)->second(),
        Sleep::for(1)->second(),
        Sleep::for(1)->second(),
    ]);
});

it('can run the check command once', function () {
    Sleep::fake();
    $called = 0;
    Event::listen(function (SharedBeat $beat) use (&$called) {
        $called++;
    });

    Artisan::call('pulse:check', ['--once' => true]);

    expect($called)->toBe(1);
    Sleep::assertNeverSlept();
});

it('exists instead of looping when on vapor', function () {
    Env::getRepository()->set('VAPOR_SSM_PATH', 1);
    Sleep::fake();
    $called = 0;
    Event::listen(function (SharedBeat $beat) use (&$called) {
        $called++;
    });

    Artisan::call('pulse:check');

    expect($called)->toBe(1);
    Sleep::assertNeverSlept();

    Env::getRepository()->clear('VAPOR_SSM_PATH');
});

it('can throttle shared beat listeners', function () {
    $iteration = 1;
    Event::listen(SharedBeat::class, $listener = new ThrottledBeatListener(CarbonInterval::seconds(3)));
    Sleep::fake();
    Sleep::whenFakingSleep(function ($duration) use (&$iteration, $listener) {
        Carbon::setTestNow(now()->add($duration));

        expect($listener->runs)->toBe(match ($iteration) {
            1, 2, 3 => 1,
            4, 5, 6 => 2,
            7, 8, 9 => 3,
            10, 11, 12 => 4,
        });

        if ($iteration === 12) {
            throw new RuntimeException('bail');
        }

        $iteration++;
    });

    try {
        Artisan::call('pulse:check');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'bail') {
            throw $e;
        }
    }
});

it('can throttle isolated beat listeners', function () {
    $iteration = 1;
    Event::listen(IsolatedBeat::class, $listener = new ThrottledBeatListener(interval: 3));
    Sleep::fake();
    Sleep::whenFakingSleep(function ($duration) use (&$iteration, $listener) {
        Carbon::setTestNow(now()->add($duration));

        expect($listener->runs)->toBe(match ($iteration) {
            1, 2, 3 => 1,
            4, 5, 6 => 2,
            7, 8, 9 => 3,
            10, 11, 12 => 4,
        });

        if ($iteration === 12) {
            throw new RuntimeException('bail');
        }

        $iteration++;
    });

    try {
        Artisan::call('pulse:check');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'bail') {
            throw $e;
        }
    }

    expect($listener->runs)->toBe(4);
});

it('does not share throttle locks across check command instances for shared beats', function () {
    Event::listen(SharedBeat::class, $listener = new ThrottledBeatListener(3));

    Artisan::call('pulse:check', ['--once' => true]);
    expect($listener->runs)->toBe(1);
    Artisan::call('pulse:check', ['--once' => true]);
    expect($listener->runs)->toBe(2);
});

it('does share throttle locks across check command instances for shared beats when on vapor', function () {
    Env::getRepository()->set('VAPOR_SSM_PATH', 1);
    Event::listen(SharedBeat::class, $listener = new ThrottledBeatListener(3));

    Artisan::call('pulse:check', ['--once' => true]);
    expect($listener->runs)->toBe(1);
    Artisan::call('pulse:check', ['--once' => true]);
    expect($listener->runs)->toBe(1);

    Env::getRepository()->clear('VAPOR_SSM_PATH');
});

it('does share throttle locks across instances for IsolatedBeats', function () {
    Event::listen(IsolatedBeat::class, $listener = new ThrottledBeatListener(3));

    Artisan::call('pulse:check', ['--once' => true]);
    expect($listener->runs)->toBe(1);
    Artisan::call('pulse:check', ['--once' => true]);
    expect($listener->runs)->toBe(1);
});

it('only fires isolated beats once per second across check command instances', function () {
    $called = 0;
    Event::listen(IsolatedBeat::class, function () use (&$called) {
        $called++;
    });

    Artisan::call('pulse:check', ['--once' => true]);
    expect($called)->toBe(1);
    Carbon::setTestNow(now()->endOfSecond());
    Artisan::call('pulse:check', ['--once' => true]);
    expect($called)->toBe(1);
    Carbon::setTestNow(now()->addMillisecond(1));
    Artisan::call('pulse:check', ['--once' => true]);
    expect($called)->toBe(2);
});

class ThrottledBeatListener
{
    use Throttling;

    public $runs = 0;

    public function __construct(protected $interval)
    {
        //
    }

    public function __invoke($event)
    {
        $this->throttle($this->interval, $event, function () {
            $this->runs++;
        });
    }
}

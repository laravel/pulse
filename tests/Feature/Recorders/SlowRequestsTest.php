<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowRequests;
use Tests\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('captures requests over the threshold', function () {
    Date::setTestNow('2000-01-02 03:04:05');
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:09');
    });

    get('test-route');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->timestamp)->toBe(946782245);
    expect($entries[0]->type)->toBe('slow_request');
    expect($entries[0]->key)->toBe('GET /test-route');
    expect($entries[0]->key_hash)->toBe(hex2bin(md5('GET /test-route')));
    expect($entries[0]->value)->toBe(4000);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('type')->orderByDesc('bucket')->get());
    expect($aggregates)->toHaveCount(4);

    expect($aggregates[0]->bucket)->toBe(946782240);
    expect($aggregates[0]->period)->toBe(60);
    expect($aggregates[0]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[0]->key)->toBe('GET /test-route');
    expect($aggregates[0]->key_hash)->toBe(hex2bin(md5('GET /test-route')));
    expect($aggregates[0]->count)->toBe(1);
    expect($aggregates[0]->value)->toBe(4000);

    expect($aggregates[1]->bucket)->toBe(946782000);
    expect($aggregates[1]->period)->toBe(360);
    expect($aggregates[1]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[1]->key)->toBe('GET /test-route');
    expect($aggregates[1]->key_hash)->toBe(hex2bin(md5('GET /test-route')));
    expect($aggregates[1]->count)->toBe(1);
    expect($aggregates[1]->value)->toBe(4000);

    expect($aggregates[2]->bucket)->toBe(946781280);
    expect($aggregates[2]->period)->toBe(1440);
    expect($aggregates[2]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[2]->key)->toBe('GET /test-route');
    expect($aggregates[2]->key_hash)->toBe(hex2bin(md5('GET /test-route')));
    expect($aggregates[2]->count)->toBe(1);
    expect($aggregates[2]->value)->toBe(4000);

    expect($aggregates[3]->period)->toBe(10080);
    expect($aggregates[3]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[3]->key)->toBe('GET /test-route');
    expect($aggregates[3]->key_hash)->toBe(hex2bin(md5('GET /test-route')));
    expect($aggregates[3]->count)->toBe(1);
    expect($aggregates[3]->value)->toBe(4000);

    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('captures slow requests per user', function () {
    Date::setTestNow('2000-01-02 03:04:05');
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:09');
    });
    $user = User::make(['id' => '4321']);

    actingAs($user)->get('test-route');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->where('type', 'slow_user_request')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->timestamp)->toBe(946782245);
    expect($entries[0]->type)->toBe('slow_user_request');
    expect($entries[0]->key)->toBe('4321');
    expect($entries[0]->key_hash)->toBe(hex2bin(md5('4321')));
    expect($entries[0]->value)->toBe(1);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('type', 'slow_user_request')->orderByDesc('bucket')->get());
    expect($aggregates)->toHaveCount(4);

    expect($aggregates[0]->bucket)->toBe(946782240);
    expect($aggregates[0]->period)->toBe(60);
    expect($aggregates[0]->type)->toBe('slow_user_request');
    expect($aggregates[0]->aggregate)->toBe('sum');
    expect($aggregates[0]->key)->toBe('4321');
    expect($aggregates[0]->key_hash)->toBe(hex2bin(md5('4321')));
    expect($aggregates[0]->count)->toBe(1);
    expect($aggregates[0]->value)->toBe(1);

    expect($aggregates[1]->bucket)->toBe(946782000);
    expect($aggregates[1]->period)->toBe(360);
    expect($aggregates[1]->type)->toBe('slow_user_request');
    expect($aggregates[0]->aggregate)->toBe('sum');
    expect($aggregates[1]->key)->toBe('4321');
    expect($aggregates[1]->key_hash)->toBe(hex2bin(md5('4321')));
    expect($aggregates[1]->count)->toBe(1);
    expect($aggregates[1]->value)->toBe(1);

    expect($aggregates[2]->bucket)->toBe(946781280);
    expect($aggregates[2]->period)->toBe(1440);
    expect($aggregates[2]->type)->toBe('slow_user_request');
    expect($aggregates[0]->aggregate)->toBe('sum');
    expect($aggregates[2]->key)->toBe('4321');
    expect($aggregates[2]->key_hash)->toBe(hex2bin(md5('4321')));
    expect($aggregates[2]->count)->toBe(1);
    expect($aggregates[2]->value)->toBe(1);

    expect($aggregates[3]->period)->toBe(10080);
    expect($aggregates[3]->type)->toBe('slow_user_request');
    expect($aggregates[0]->aggregate)->toBe('sum');
    expect($aggregates[3]->key)->toBe('4321');
    expect($aggregates[3]->key_hash)->toBe(hex2bin(md5('4321')));
    expect($aggregates[3]->count)->toBe(1);
    expect($aggregates[3]->value)->toBe(1);

    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('captures requests equal to the threshold', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 1001);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:06.001');
    });

    get('test-route');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->get())->toHaveCount(1));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->get())->toHaveCount(4));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('ignores requests under the threshold', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 1001);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:06.000');
    });

    get('test-route');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('can ignore requests based on config', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowRequests::class.'.ignore', [
        '#^/test-route#',
    ]);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:06');
    });

    get('test-route');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('quietly fails if an exception is thrown while preparing the entry payload', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Date::setTestNow('2000-01-02 03:04:05');
    Pulse::register([ExceptionThrowingRecorder::class => []]);
    $exceptions = [];
    Pulse::handleExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });

    get('test-route');

    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Opps!');
    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('ignores livewire update requests from an ignored path', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::post('livewire/update', function () {
        Date::setTestNow('2000-01-02 03:04:09');
    })->name('livewire.update');
    Config::set('pulse.recorders.'.SlowRequests::class.'.ignore', [
        '#^/test-route#',
    ]);

    post('/livewire/update', [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'path' => 'test-route',
                    ],
                ]),
            ],
        ],
    ]);

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('captures the requests "via" route when using livewire', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::post('livewire/update', function () {
        Date::setTestNow('2000-01-02 03:04:09');
    })->name('livewire.update');

    post('/livewire/update', [
        'components' => [
            [
                'snapshot' => json_encode([
                    'memo' => [
                        'path' => 'test-route',
                    ],
                ]),
            ],
        ],
    ]);

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->timestamp)->toBe(946782245);
    expect($entries[0]->type)->toBe('slow_request');
    expect($entries[0]->key)->toBe('POST /test-route (/livewire/update)');
    expect($entries[0]->key_hash)->toBe(hex2bin(md5('POST /test-route (/livewire/update)')));
    expect($entries[0]->value)->toBe(4000);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('type')->orderByDesc('bucket')->get());
    expect($aggregates)->toHaveCount(4);

    expect($aggregates[0]->bucket)->toBe(946782240);
    expect($aggregates[0]->period)->toBe(60);
    expect($aggregates[0]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[0]->key)->toBe('POST /test-route (/livewire/update)');
    expect($aggregates[0]->key_hash)->toBe(hex2bin(md5('POST /test-route (/livewire/update)')));
    expect($aggregates[0]->count)->toBe(1);
    expect($aggregates[0]->value)->toBe(4000);

    expect($aggregates[1]->bucket)->toBe(946782000);
    expect($aggregates[1]->period)->toBe(360);
    expect($aggregates[1]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[1]->key)->toBe('POST /test-route (/livewire/update)');
    expect($aggregates[1]->key_hash)->toBe(hex2bin(md5('POST /test-route (/livewire/update)')));
    expect($aggregates[1]->count)->toBe(1);
    expect($aggregates[1]->value)->toBe(4000);

    expect($aggregates[2]->bucket)->toBe(946781280);
    expect($aggregates[2]->period)->toBe(1440);
    expect($aggregates[2]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[2]->key)->toBe('POST /test-route (/livewire/update)');
    expect($aggregates[2]->key_hash)->toBe(hex2bin(md5('POST /test-route (/livewire/update)')));
    expect($aggregates[2]->count)->toBe(1);
    expect($aggregates[2]->value)->toBe(4000);

    expect($aggregates[3]->period)->toBe(10080);
    expect($aggregates[3]->type)->toBe('slow_request');
    expect($aggregates[0]->aggregate)->toBe('max');
    expect($aggregates[3]->key)->toBe('POST /test-route (/livewire/update)');
    expect($aggregates[3]->key_hash)->toBe(hex2bin(md5('POST /test-route (/livewire/update)')));
    expect($aggregates[3]->count)->toBe(1);
    expect($aggregates[3]->value)->toBe(4000);

    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('only records known routes', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Date::setTestNow('2000-01-02 03:04:05');

    get('some-route-that-does-not-exit')->assertNotFound();

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('handles routes with domains', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::domain('{account}.example.com')->get('users', fn () => 'account users');
    Route::get('users', fn () => 'global users');

    get('http://foo.example.com/users')->assertContent('account users');
    get('http://example.com/users')->assertContent('global users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(2);
    expect($entries[0]->key)->toBe('GET {account}.example.com/users');
    expect($entries[1]->key)->toBe('GET /users');
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(8));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('can sample', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowRequests::class.'.sample_rate', 0.1);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:09');
    });

    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toEqualWithDelta(1, 4));
});

it('can sample at zero', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowRequests::class.'.sample_rate', 0);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:09');
    });

    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(0));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

it('can sample at one', function () {
    Config::set('pulse.recorders.'.SlowRequests::class.'.threshold', 0);
    Config::set('pulse.recorders.'.SlowRequests::class.'.sample_rate', 1);
    Date::setTestNow('2000-01-02 03:04:05');
    Route::get('test-route', function () {
        Date::setTestNow('2000-01-02 03:04:09');
    });

    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');
    get('test-route');

    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(10));
    Pulse::ignore(fn () => expect(DB::table('pulse_aggregates')->count())->toBe(4));
    Pulse::ignore(fn () => expect(DB::table('pulse_values')->count())->toBe(0));
});

class ExceptionThrowingRecorder
{
    public function register(callable $record, Application $app): void
    {
        $app[Kernel::class]->whenRequestLifecycleIsLongerThan(-1, $record);
    }

    public function record(): void
    {
        throw new RuntimeException('Opps!');
    }
}

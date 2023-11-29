<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Exceptions;

it('ingests exceptions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    report(new RuntimeException('Expected exception.'));

    expect(Pulse::queue())->toHaveCount(1);
    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));

    Pulse::store();

    expect(Pulse::queue())->toHaveCount(0);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception',
    ]);
    $key = json_decode($entries[0]->key);
    expect($key[0])->toBe('RuntimeException');
    expect($key[1])->toStartWith(__FILE__.':');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'exception',
        'aggregate' => 'count',
        'value' => 1,
    ]);
    $key = json_decode($aggregates[0]->key);
    expect($key[0])->toBe('RuntimeException');
    expect($key[1])->toStartWith(__FILE__.':');
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'exception',
        'aggregate' => 'max',
        'value' => now()->timestamp,
    ]);
    $key = json_decode($aggregates[1]->key);
    expect($key[0])->toBe('RuntimeException');
    expect($key[1])->toStartWith(__FILE__.':');
});

it('can disable capturing the location', function () {
    Config::set('pulse.recorders.'.Exceptions::class.'.location', false);
    Carbon::setTestNow('2000-01-02 03:04:05');

    report(new RuntimeException('Expected exception.'));
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception',
        'value' => now()->timestamp,
    ]);
    $key = json_decode($entries[0]->key);
    expect($key)->toBe(['RuntimeException', null]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'exception',
        'aggregate' => 'count',
        'value' => 1,
    ]);
    $key = json_decode($aggregates[0]->key);
    expect($key)->toBe(['RuntimeException', null]);
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'exception',
        'aggregate' => 'max',
        'value' => now()->timestamp,
    ]);
    $key = json_decode($aggregates[1]->key);
    expect($key)->toBe(['RuntimeException', null]);
});

it('can manually report exceptions', function () {
    Carbon::setTestNow('2000-01-01 00:00:00');

    Pulse::report(new MyReportedException('Hello, Pulse!'));
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception',
        'value' => now()->timestamp,
    ]);
    $key = json_decode($entries[0]->key);
    expect($key[0])->toBe('MyReportedException');
    expect($key[1])->toStartWith(__FILE__.':');
});

it('can ignore exceptions', function () {
    Config::set('pulse.recorders.'.Exceptions::class.'.ignore', [
        '/^Tests\\\\Feature\\\\Exceptions/',
    ]);

    report(new \Tests\Feature\Exceptions\MyException('Ignored exception'));

    expect(Pulse::queue())->toHaveCount(0);
});

it('can sample', function () {
    Config::set('pulse.recorders.'.Exceptions::class.'.sample_rate', 0.1);

    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());

    expect(Pulse::queue()->count())->toEqualWithDelta(1, 4);

    Pulse::flush();
});

it('can sample at zero', function () {
    Config::set('pulse.recorders.'.Exceptions::class.'.sample_rate', 0);

    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());

    expect(Pulse::queue())->toHaveCount(0);

    Pulse::flush();
});

it('can sample at one', function () {
    Config::set('pulse.recorders.'.Exceptions::class.'.sample_rate', 1);

    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());
    report(new MyReportedException());

    expect(Pulse::queue())->toHaveCount(10);

    Pulse::flush();
});

class MyReportedException extends Exception
{
    //
}

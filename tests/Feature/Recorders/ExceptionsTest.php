<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Exceptions;
use Laravel\Pulse\Value;

it('ingests exceptions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    report(new RuntimeException('Expected exception.'));

    expect(Pulse::entries())->toHaveCount(2);
    Pulse::ignore(fn () => expect(DB::table('pulse_entries')->count())->toBe(0));

    Pulse::store(app(Ingest::class));

    expect(Pulse::entries())->toHaveCount(0);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception',
    ]);
    expect($entries[0]->key)->toStartWith('RuntimeException::'.__FILE__.':');
    $values = Pulse::ignore(fn () => DB::table('pulse_values')->get());
    expect($values)->toHaveCount(1);
    expect($values[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception:latest',
        'value' => now()->timestamp,
    ]);
    expect($values[0]->key)->toStartWith('RuntimeException::'.__FILE__.':');
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(4);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'exception:count',
        'value' => 1,
    ]);
    expect($aggregates[0]->key)->toStartWith('RuntimeException::'.__FILE__.':');
});

it('can disable capturing the location', function () {
    Config::set('pulse.recorders.'.Exceptions::class.'.location', false);
    Carbon::setTestNow('2000-01-02 03:04:05');

    report(new RuntimeException('Expected exception.'));
    Pulse::store(app(Ingest::class));

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception',
        'key' => 'RuntimeException',
    ]);
    $values = Pulse::ignore(fn () => DB::table('pulse_values')->get());
    expect($values)->toHaveCount(1);
    expect($values[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception:latest',
        'key' => 'RuntimeException',
        'value' => now()->timestamp,
    ]);
});

it('can manually report exceptions', function () {
    Carbon::setTestNow('2000-01-01 00:00:00');

    Pulse::report(new MyReportedException('Hello, Pulse!'));
    Pulse::store(app(Ingest::class));

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception',
    ]);
    expect($entries[0]->key)->toStartWith('MyReportedException::'.__FILE__.':');
    $values = Pulse::ignore(fn () => DB::table('pulse_values')->get());
    expect($values)->toHaveCount(1);
    expect($values[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'exception:latest',
        'value' => now()->timestamp,
    ]);
    expect($entries[0]->key)->toStartWith('MyReportedException::'.__FILE__.':');
});

it('can ignore exceptions', function () {
    Config::set('pulse.recorders.'.Exceptions::class.'.ignore', [
        '/^Tests\\\\Feature\\\\Exceptions/',
    ]);

    report(new \Tests\Feature\Exceptions\MyException('Ignored exception'));

    expect(Pulse::entries())->toHaveCount(0);
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

    [$entries, $values] = Pulse::entries()->partition(fn ($entry) => $entry instanceof Entry);

    expect(count($entries))->toEqualWithDelta(1, 4);
    expect(count($values))->toEqual(count($entries));

    Pulse::flushEntries();
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

    expect(count(Pulse::entries()))->toBe(0);

    Pulse::flushEntries();
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

    expect(count(Pulse::entries()->filter(fn ($entry) => $entry instanceof Entry)))->toBe(10);
    expect(count(Pulse::entries()->filter(fn ($entry) => $entry instanceof Value)))->toBe(10);

    Pulse::flushEntries();
});

class MyReportedException extends Exception
{
    //
}

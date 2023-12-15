<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Storage\DatabaseStorage;

it('trims values at or past expiry', function () {
    Date::setTestNow('2000-01-01 00:00:04');
    Pulse::set('type', 'foo', 'value');
    Date::setTestNow('2000-01-01 00:00:05');
    Pulse::set('type', 'bar', 'value');
    Date::setTestNow('2000-01-01 00:00:06');
    Pulse::set('type', 'baz', 'value');
    Pulse::ingest();

    Pulse::stopRecording();
    Date::setTestNow('2000-01-08 00:00:05');
    App::make(DatabaseStorage::class)->trim();

    expect(DB::table('pulse_values')->pluck('key')->all())->toBe(['baz']);
});

it('trims entries at or after week after timestamp', function () {
    Date::setTestNow('2000-01-01 00:00:04');
    Pulse::record('foo', 'xxxx', 1);
    Date::setTestNow('2000-01-01 00:00:05');
    Pulse::record('bar', 'xxxx', 1);
    Date::setTestNow('2000-01-01 00:00:06');
    Pulse::record('baz', 'xxxx', 1);
    Pulse::ingest();

    Pulse::stopRecording();
    Date::setTestNow('2000-01-08 00:00:05');
    App::make(DatabaseStorage::class)->trim();

    expect(DB::table('pulse_entries')->pluck('type')->all())->toBe(['baz']);
});

it('trims aggregates once the 1 hour bucket is no longer relevant', function () {
    Date::setTestNow('2000-01-01 00:00:59'); // Bucket: 2000-01-01 00:00:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->count()))->toBe(1);

    Date::setTestNow('2000-01-01 00:01:00'); // Bucket: 2000-01-01 00:01:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 60)->count()))->toBe(2);

    Pulse::stopRecording();
    Date::setTestNow('2000-01-01 00:59:59'); // 1 second before the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 60)->count())->toBe(2);

    Date::setTestNow('2000-01-01 01:00:00'); // The second the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 60)->count())->toBe(1);
});

it('trims aggregates once the 6 hour bucket is no longer relevant', function () {
    Date::setTestNow('2000-01-01 00:05:59'); // Bucket: 2000-01-01 00:00:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 360)->count()))->toBe(1);

    Date::setTestNow('2000-01-01 00:06:00'); // Bucket: 2000-01-01 00:06:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 360)->count()))->toBe(2);

    Pulse::stopRecording();
    Date::setTestNow('2000-01-01 05:59:59'); // 1 second before the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 360)->count())->toBe(2);

    Date::setTestNow('2000-01-01 06:00:00'); // The second the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 360)->count())->toBe(1);
});

it('trims aggregates once the 24 hour bucket is no longer relevant', function () {
    Date::setTestNow('2000-01-01 00:23:59'); // Bucket: 2000-01-01 00:00:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 1440)->count()))->toBe(1);

    Date::setTestNow('2000-01-01 00:24:00'); // Bucket: 2000-01-01 00:24:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 1440)->count()))->toBe(2);

    Pulse::stopRecording();
    Date::setTestNow('2000-01-01 23:35:59'); // 1 second before the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 1440)->count())->toBe(2);

    Date::setTestNow('2000-01-02 00:00:00'); // The second the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 1440)->count())->toBe(1);
});

it('trims aggregates once the 7 day bucket is no longer relevant', function () {
    Date::setTestNow('2000-01-01 02:23:59'); // Bucket: 1999-12-31 23:36:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 10080)->count()))->toBe(1);

    Date::setTestNow('2000-01-01 02:24:00'); // Bucket: 2000-01-01 02:24:00
    Pulse::record('foo', 'xxxx', 1)->count();
    Pulse::ingest();
    expect(Pulse::ignore(fn () => DB::table('pulse_aggregates')->where('period', 10080)->count()))->toBe(2);

    Pulse::stopRecording();
    Date::setTestNow('2000-01-07 23:35:59'); // 1 second before the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 10080)->count())->toBe(2);

    Date::setTestNow('2000-01-07 23:36:00'); // The second the oldest bucket become irrelevant.
    App::make(DatabaseStorage::class)->trim();
    expect(DB::table('pulse_aggregates')->where('period', 10080)->count())->toBe(1);
});

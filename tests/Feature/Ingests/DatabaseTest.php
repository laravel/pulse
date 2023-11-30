<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Storage\DatabaseStorage;

it('trims values at or past expiry', function () {
    Date::setTestNow('2000-01-08 00:00:05');
    DB::table('pulse_values')->insert([
        ['type' => 'type', 'key' => 'foo', 'value' => 'value', 'timestamp' => CarbonImmutable::parse('2000-01-01 00:00:04')->getTimestamp()],
        ['type' => 'type', 'key' => 'bar', 'value' => 'value', 'timestamp' => CarbonImmutable::parse('2000-01-01 00:00:05')->getTimestamp()],
        ['type' => 'type', 'key' => 'baz', 'value' => 'value', 'timestamp' => CarbonImmutable::parse('2000-01-01 00:00:06')->getTimestamp()],
    ]);

    App::make(DatabaseStorage::class)->trim();

    expect(DB::table('pulse_values')->pluck('key')->all())->toBe(['baz']);
});

it('trims entries at or after week after timestamp', function () {
    Date::setTestNow('2000-01-08 00:00:05');
    DB::table('pulse_entries')->insert([
        ['type' => 'foo', 'key' => 'xxxx', 'value' => 1, 'timestamp' => CarbonImmutable::parse('2000-01-01 00:00:04')->getTimestamp()],
        ['type' => 'bar', 'key' => 'xxxx', 'value' => 1, 'timestamp' => CarbonImmutable::parse('2000-01-01 00:00:05')->getTimestamp()],
        ['type' => 'baz', 'key' => 'xxxx', 'value' => 1, 'timestamp' => CarbonImmutable::parse('2000-01-01 00:00:06')->getTimestamp()],
    ]);

    App::make(DatabaseStorage::class)->trim();

    expect(DB::table('pulse_entries')->pluck('type')->all())->toBe(['baz']);
});

it('trims aggregates once the bucket is no longer relevant', function () {
    Date::setTestNow('2000-01-08 01:01:05');

    DB::table('pulse_aggregates')->insert([
        ['period' => 60, 'type' => 'foo:60', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-08 00:01:04')->getTimestamp()],
        ['period' => 60, 'type' => 'bar:60', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-08 00:01:05')->getTimestamp()],
        ['period' => 60, 'type' => 'baz:60', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-08 00:01:06')->getTimestamp()],
        ['period' => 360, 'type' => 'foo:360', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-07 19:01:04')->getTimestamp()],
        ['period' => 360, 'type' => 'bar:360', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-07 19:01:05')->getTimestamp()],
        ['period' => 360, 'type' => 'baz:360', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-07 19:01:06')->getTimestamp()],
        ['period' => 1440, 'type' => 'foo:1440', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-07 01:01:04')->getTimestamp()],
        ['period' => 1440, 'type' => 'bar:1440', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-07 01:01:05')->getTimestamp()],
        ['period' => 1440, 'type' => 'baz:1440', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-07 01:01:06')->getTimestamp()],
        ['period' => 10080, 'type' => 'foo:10080', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-01 01:01:04')->getTimestamp()],
        ['period' => 10080, 'type' => 'bar:10080', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-01 01:01:05')->getTimestamp()],
        ['period' => 10080, 'type' => 'baz:10080', 'key' => 'xxxx', 'aggregate' => 'sum', 'value' => 1, 'count' => 1, 'bucket' => CarbonImmutable::parse('2000-01-01 01:01:06')->getTimestamp()],
    ]);

    App::make(DatabaseStorage::class)->trim();

    expect(DB::table('pulse_aggregates')->pluck('type')->all())->toEqualCanonicalizing([
        'baz:60',
        'baz:360',
        'baz:1440',
        'baz:10080',
    ]);
});

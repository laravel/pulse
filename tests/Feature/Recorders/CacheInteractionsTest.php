<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\CacheInteractions;

it('ingests cache interactions', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::put('hit-key', 1);
    Cache::get('hit-key');
    Cache::get('miss-key');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(2);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'cache_hit',
        'key' => 'hit-key',
        'value' => 1,
    ]);
    expect($entries[1])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'cache_miss',
        'key' => 'miss-key',
        'value' => 1,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'cache_hit',
        'aggregate' => 'count',
        'key' => 'hit-key',
        'value' => 1,
    ]);
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'cache_miss',
        'aggregate' => 'count',
        'key' => 'miss-key',
        'value' => 1,
    ]);
});

it('ignores internal illuminate cache interactions', function () {
    Cache::get('illuminate:');

    expect(Pulse::store())->toBe(0);
});

it('ignores internal pulse cache interactions', function () {
    Cache::get('laravel:pulse:');

    expect(Pulse::store())->toBe(0);
});

it('stores the original keys by default', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Cache::get('users:1234:profile');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'cache_miss',
        'key' => 'users:1234:profile',
        'value' => 1,
    ]);
});

it('can normalize cache keys', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.recorders.'.CacheInteractions::class.'.groups', [
        '/users:\d+:profile/' => 'users:{user}:profile',
    ]);
    Cache::get('users:1234:profile');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'cache_miss',
        'key' => 'users:{user}:profile',
        'value' => 1,
    ]);
});

it('can use back references in normalized cache keys', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.recorders.'.CacheInteractions::class.'.groups', [
        '/^([^:]+):([^:]+):baz/' => '\2:\1',
    ]);
    Cache::get('foo:bar:baz');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'cache_miss',
        'key' => 'bar:foo',
        'value' => 1,
    ]);
});

it('uses the original key if no matching pattern is found', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.recorders.'.CacheInteractions::class.'.groups', [
        '/\d/' => 'foo',
    ]);
    Cache::get('actual-key');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'cache_miss',
        'key' => 'actual-key',
        'value' => 1,
    ]);
});

it('can provide regex flags in normalization key', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');

    Config::set('pulse.recorders.'.CacheInteractions::class.'.groups', [
        '/foo/i' => 'lowercase-key',
        '/FOO/i' => 'uppercase-key',
    ]);
    Cache::get('FOO');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'cache_miss',
        'key' => 'lowercase-key',
        'value' => 1,
    ]);
});

it('can ignore keys', function () {
    Config::set('pulse.recorders.'.CacheInteractions::class.'.ignore', [
        '/^laravel:pulse:/', // Pulse keys
    ]);

    Cache::get('laravel:pulse:foo:bar');

    expect(Pulse::store())->toBe(0);
});

it('can sample', function () {
    Config::set('pulse.recorders.'.CacheInteractions::class.'.sample_rate', 0.1);

    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');
    Cache::get('foo');

    expect(Pulse::store())->toEqualWithDelta(1, 4);
});

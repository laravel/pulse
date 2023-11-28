<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowOutgoingRequests;

it('ingests slow outgoing http requests', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(fn () => Http::response('ok'));

    Http::get('https://laravel.com');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => 'GET https://laravel.com',
        'value' => 0,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'slow_outgoing_request',
        'aggregate' => 'count',
        'key' => 'GET https://laravel.com',
        'value' => 1,
    ]);
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) floor(now()->timestamp / 60) * 60,
        'period' => 60,
        'type' => 'slow_outgoing_request',
        'aggregate' => 'max',
        'key' => 'GET https://laravel.com',
        'value' => 0,
    ]);
});

it('ignores fast requests', function () {
    Http::fake(fn () => Http::response('ok'));

    Http::get('https://laravel.com');

    expect(Pulse::entries())->toHaveCount(0);
});

it('captures failed requests', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(['https://laravel.com' => Http::response('error', status: 500)]);

    Http::get('https://laravel.com');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => 'GET https://laravel.com',
        'value' => 0,
    ]);
});

it('stores the original URI by default', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(['https://laravel.com*' => Http::response('ok')]);

    Http::get('https://laravel.com?foo=123');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => 'GET https://laravel.com?foo=123',
        'value' => 0,
    ]);
});

it('can normalize URI', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(fn () => Http::response('ok'));

    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.groups', [
        '#^https://github\.com/([^/]+)/([^/]+)/commits/([^/]+)$#' => 'github.com/{user}/{repo}/commits/{branch}',
    ]);
    Http::get('https://github.com/laravel/pulse/commits/1.x');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => 'GET github.com/{user}/{repo}/commits/{branch}',
        'value' => 0,
    ]);
});

it('can use back references in normalized URI', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(fn () => Http::response('ok'));

    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.groups', [
        '#^https?://([^/]+).*$#' => '\1/*',
    ]);
    Http::get('https://github.com/laravel/pulse/commits/1.x');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => 'GET github.com/*',
        'value' => 0,
    ]);
});

it('can provide regex flags in normalization key', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(fn () => Http::response('ok'));

    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.groups', [
        '/parameter/i' => 'lowercase-parameter',
        '/PARAMETER/i' => 'uppercase-parameter',
    ]);
    Http::get('https://github.com?PARAMETER=123');
    Pulse::store();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => 'GET https://github.com?lowercase-parameter=123',
        'value' => 0,
    ]);
});

it('can ignore outgoing requests', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Http::fake(fn () => Http::response('ok'));
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.ignore', [
        '#^http://127\.0\.0\.1:13714#', // Inertia SSR
    ]);

    Http::get('http://127.0.0.1:13714/render');

    expect(Pulse::entries())->toHaveCount(0);
});

it('can sample', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Http::fake(fn () => Http::response('ok'));
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.sample_rate', 0.1);

    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');

    expect(count(Pulse::entries()))->toEqualWithDelta(1, 4);

    Pulse::flushEntries();
});

it('can sample at zero', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Http::fake(fn () => Http::response('ok'));
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.sample_rate', 0);

    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');

    expect(count(Pulse::entries()))->toBe(0);

    Pulse::flushEntries();
});

it('can sample at one', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Http::fake(fn () => Http::response('ok'));
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.sample_rate', 1);

    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');

    expect(count(Pulse::entries()))->toBe(10);

    Pulse::flushEntries();
});

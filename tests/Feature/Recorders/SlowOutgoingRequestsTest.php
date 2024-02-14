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
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => json_encode(['GET', 'https://laravel.com']),
        'value' => 0,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->orderBy('aggregate')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'slow_outgoing_request',
        'aggregate' => 'count',
        'key' => json_encode(['GET', 'https://laravel.com']),
        'value' => 1,
    ]);
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'slow_outgoing_request',
        'aggregate' => 'max',
        'key' => json_encode(['GET', 'https://laravel.com']),
        'value' => 0,
    ]);
});

it('ignores fast requests', function () {
    Http::fake(fn () => Http::response('ok'));

    Http::get('https://laravel.com');

    expect(Pulse::ingest())->toBe(0);
});

it('captures failed requests', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(['https://laravel.com' => Http::response('error', status: 500)]);

    Http::get('https://laravel.com');
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => json_encode(['GET', 'https://laravel.com']),
        'value' => 0,
    ]);
});

it('stores the original URI by default', function () {
    Config::set('pulse.recorders.'.SlowOutgoingRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(['https://laravel.com*' => Http::response('ok')]);

    Http::get('https://laravel.com?foo=123');
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => json_encode(['GET', 'https://laravel.com?foo=123']),
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
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => json_encode(['GET', 'github.com/{user}/{repo}/commits/{branch}']),
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
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => json_encode(['GET', 'github.com/*']),
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
    Pulse::ingest();

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_outgoing_request',
        'key' => json_encode(['GET', 'https://github.com?lowercase-parameter=123']),
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

    expect(Pulse::ingest())->toBe(0);
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

    expect(Pulse::ingest())->toEqualWithDelta(1, 4);
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

    expect(Pulse::ingest())->toBe(0);
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

    expect(Pulse::ingest())->toBe(10);
});

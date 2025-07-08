<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Servers;

it('records server information', function () {
    Config::set('pulse.recorders.'.Servers::class.'.server_name', 'Foo');
    Date::setTestNow(Date::now()->startOfMinute());
    event(new SharedBeat(CarbonImmutable::now(), 'instance-id'));
    Pulse::ingest();

    expect(Pulse::ignore(fn () => DB::table('pulse_entries')->count()))->toBe(0);

    $value = Pulse::ignore(fn () => DB::table('pulse_values')->sole());
    expect($value->type)->toBe('system');
    expect($value->key)->toBe('foo');
    expect($value->timestamp)->toBe(Date::now()->startOfMinute()->timestamp);
    $payload = json_decode($value->value);
    expect($payload->name)->toBe('Foo');
    expect($payload->cpu)->toBeGreaterThanOrEqual(0);
    expect($payload->cpu)->toBeLessThanOrEqual(100);
    expect($payload->memory_used)->toBeGreaterThan(0);
    expect($payload->memory_total)->toBeGreaterThan(0);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->get());
    expect($aggregates->count())->toBe(8);
    expect($aggregates->pluck('type')->unique()->values()->all())->toBe(['cpu', 'memory']);
    expect($aggregates->pluck('period')->unique()->values()->all())->toBe([60, 360, 1440, 10080]);
    expect($aggregates->pluck('key')->unique()->values()->all())->toBe(['foo']);
    expect($aggregates->pluck('aggregate')->unique()->values()->all())->toBe(['avg']);
});

it('can customise CPU and memory resolution', function () {
    Config::set('pulse.recorders.'.Servers::class.'.server_name', 'Foo');
    Date::setTestNow(Date::now()->startOfMinute());

    Servers::detectCpuUsing(fn () => 987654321);
    Servers::detectMemoryUsing(fn () => [
        'total' => 123456789,
        'used' => 1234,
    ]);
    event(new SharedBeat(CarbonImmutable::now(), 'instance-id'));
    Pulse::ingest();

    $value = Pulse::ignore(fn () => DB::table('pulse_values')->sole());

    $payload = json_decode($value->value);
    expect($payload->cpu)->toBe(987654321);
    expect($payload->memory_used)->toBe(1234);
    expect($payload->memory_total)->toBe(123456789);

    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('type')->get());
    expect($aggregates->count())->toBe(8);
    expect($aggregates->pluck('type')->unique()->values()->all())->toBe(['cpu', 'memory']);
    expect($aggregates->pluck('value')->unique()->values()->all())->toEqual(['987654321.00', '1234.00']);

    Servers::detectCpuUsing(null);
    Servers::detectMemoryUsing(null);
});

it('skips missing filesystems when recording events', function () {
    Pulse::handleExceptionsUsing(function () {
        //
    });
    Config::set('pulse.recorders.'.Servers::class.'.directories', ['/', '/nonexistent']);
    Date::setTestNow(Date::now()->startOfMinute());

    event(new SharedBeat(CarbonImmutable::now(), 'instance-id'));
    Pulse::ingest();

    $value = Pulse::ignore(fn () => DB::table('pulse_values')->sole());

    $payload = json_decode($value->value);
    expect($payload->storage)->toHaveCount(1);
});

it('includes container information in server name when in a container', function () {
    Config::set('pulse.recorders.'.Servers::class.'.server_name', 'Foo');
    Date::setTestNow(Date::now()->startOfMinute());

    // Set container detection to return true
    Servers::detectContainerUsing(fn () => true);

    event(new SharedBeat(CarbonImmutable::now(), 'instance-id'));
    Pulse::ingest();

    $value = Pulse::ignore(fn () => DB::table('pulse_values')->sole());
    $payload = json_decode($value->value);

    // Verify server name includes container information
    expect($payload->name)->toContain('Foo');
    expect($payload->name)->toContain('[containerized]');

    // Reset container detection
    Servers::detectContainerUsing(null);
});

it('uses container-specific methods for CPU and memory when in a container', function () {
    Config::set('pulse.recorders.'.Servers::class.'.server_name', 'Foo');
    Date::setTestNow(Date::now()->startOfMinute());

    // Set container detection to return true
    Servers::detectContainerUsing(fn () => true);

    // Set custom CPU and memory detection to simulate container-specific methods
    Servers::detectCpuUsing(fn () => 42);
    Servers::detectMemoryUsing(fn () => [
        'total' => 8192, // 8GB
        'used' => 4096,  // 4GB
    ]);

    event(new SharedBeat(CarbonImmutable::now(), 'instance-id'));
    Pulse::ingest();

    $value = Pulse::ignore(fn () => DB::table('pulse_values')->sole());
    $payload = json_decode($value->value);

    // Verify CPU and memory values
    expect($payload->cpu)->toBe(42);
    expect($payload->memory_total)->toBe(8192);
    expect($payload->memory_used)->toBe(4096);

    // Reset container, CPU, and memory detection
    Servers::detectContainerUsing(null);
    Servers::detectCpuUsing(null);
    Servers::detectMemoryUsing(null);
});

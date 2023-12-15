<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\Servers;

it('records server information', function () {
    Config::set('pulse.recorders.'.Servers::class.'.server_name', 'Foo');
    Date::setTestNow(Date::now()->startOfMinute());
    event(app(SharedBeat::class));
    Pulse::store();

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

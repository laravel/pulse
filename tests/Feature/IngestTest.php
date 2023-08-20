<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Facades\Pulse;

beforeEach(function () {
    Pulse::handleExceptionsUsing(fn ($e) => throw $e);

    Pulse::ignore(fn () => Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    }));
});

it('ingests queries', function () {
    Config::set('pulse.slow_query_threshold', 0);

    expect(Pulse::queue())->toHaveCount(0);

    DB::table('users')->count();
    expect(Pulse::queue())->toHaveCount(1);
    expect(DB::table('pulse_queries')->count())->toBe(0);

    Pulse::store();
    expect(Pulse::queue())->toHaveCount(0);
    expect(DB::table('pulse_queries')->count())->toBe(1);
});

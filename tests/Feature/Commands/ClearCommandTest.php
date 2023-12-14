<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;

it('clears Pulse data', function () {
    Pulse::set('foo', 'bar', 'baz');
    Pulse::record('foo', 'bar', 123)->max()->count();
    Pulse::ingest();

    Pulse::ignore(function () {
        expect(DB::table('pulse_values')->count())->toBe(1);
        expect(DB::table('pulse_entries')->count())->toBe(1);
        expect(DB::table('pulse_aggregates')->count())->toBe(8);
    });

    Artisan::call('pulse:clear');

    Pulse::ignore(function () {
        expect(DB::table('pulse_values')->count())->toBe(0);
        expect(DB::table('pulse_entries')->count())->toBe(0);
        expect(DB::table('pulse_aggregates')->count())->toBe(0);
    });
});

it('can specify types', function () {
    Pulse::set('keep-me', 'foo', 'bar');
    Pulse::set('delete-me', 'foo', 'bar');
    Pulse::record('keep-me', 'foo', 123)->max()->count();
    Pulse::record('delete-me', 'foo', 123)->max()->count();
    Pulse::ingest();

    Pulse::ignore(function () {
        expect(DB::table('pulse_values')->count())->toBe(2);
        expect(DB::table('pulse_entries')->count())->toBe(2);
        expect(DB::table('pulse_aggregates')->count())->toBe(16);
    });

    Artisan::call('pulse:clear --type delete-me');

    Pulse::ignore(function () {
        expect(DB::table('pulse_values')->where('type', 'keep-me')->count())->toBe(1);
        expect(DB::table('pulse_values')->where('type', 'delete-me')->count())->toBe(0);
        expect(DB::table('pulse_entries')->where('type', 'keep-me')->count())->toBe(1);
        expect(DB::table('pulse_entries')->where('type', 'delete-me')->count())->toBe(0);
        expect(DB::table('pulse_aggregates')->where('type', 'keep-me')->count())->toBe(8);
        expect(DB::table('pulse_aggregates')->where('type', 'delete-me')->count())->toBe(0);
    });
});

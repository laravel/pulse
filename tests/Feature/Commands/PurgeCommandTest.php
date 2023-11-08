<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;

it('can purge all Pulse tables', function () {
    Pulse::ignore(function () {
        DB::table('pulse_cache_interactions')->insert([
            ['date' => '2000-01-02 03:04:05', 'key' => 'foo', 'hit' => true],
        ]);
        DB::table('pulse_exceptions')->insert([
            ['date' => '2000-01-02 03:04:05', 'class' => 'RuntimeException', 'location' => 'app/Foo.php'],
        ]);
        DB::table('pulse_jobs')->insert([
            ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\MyJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05'],
        ]);
        DB::table('pulse_system_stats')->insert([
            ['date' => '2000-01-02 03:04:00', 'server' => 'Web 1', 'cpu_percent' => 12, 'memory_used' => 1234, 'memory_total' => 2468, 'storage' => json_encode([['directory' => '/', 'used' => 123, 'total' => 456]])],
        ]);
        DB::table('pulse_outgoing_requests')->insert([
            ['date' => '2000-01-02 03:04:05', 'uri' => 'GET http://example.com', 'duration' => 1234, 'slow' => true],
        ]);
        DB::table('pulse_slow_queries')->insert([
            ['date' => '2000-01-02 03:04:05', 'sql' => 'select * from `users`', 'location' => 'app/Foo.php:123', 'duration' => 1234],
        ]);
        DB::table('pulse_requests')->insert([
            ['date' => '2000-01-02 03:04:05', 'route' => 'GET /users', 'duration' => 500, 'slow' => false],
        ]);
    });
    expect(DB::table('pulse_cache_interactions')->count())->toBe(1);
    expect(DB::table('pulse_exceptions')->count())->toBe(1);
    expect(DB::table('pulse_jobs')->count())->toBe(1);
    expect(DB::table('pulse_system_stats')->count())->toBe(1);
    expect(DB::table('pulse_outgoing_requests')->count())->toBe(1);
    expect(DB::table('pulse_slow_queries')->count())->toBe(1);
    expect(DB::table('pulse_requests')->count())->toBe(1);

    Artisan::call('pulse:purge');

    expect(DB::table('pulse_cache_interactions')->count())->toBe(0);
    expect(DB::table('pulse_exceptions')->count())->toBe(0);
    expect(DB::table('pulse_jobs')->count())->toBe(0);
    expect(DB::table('pulse_system_stats')->count())->toBe(0);
    expect(DB::table('pulse_outgoing_requests')->count())->toBe(0);
    expect(DB::table('pulse_slow_queries')->count())->toBe(0);
    expect(DB::table('pulse_requests')->count())->toBe(0);
});

it('can exclude recorders', function () {
    Pulse::ignore(function () {
        DB::table('pulse_cache_interactions')->insert([
            ['date' => '2000-01-02 03:04:05', 'key' => 'foo', 'hit' => true],
        ]);
        DB::table('pulse_exceptions')->insert([
            ['date' => '2000-01-02 03:04:05', 'class' => 'RuntimeException', 'location' => 'app/Foo.php'],
        ]);
        DB::table('pulse_jobs')->insert([
            ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\MyJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05'],
        ]);
        DB::table('pulse_system_stats')->insert([
            ['date' => '2000-01-02 03:04:00', 'server' => 'Web 1', 'cpu_percent' => 12, 'memory_used' => 1234, 'memory_total' => 2468, 'storage' => json_encode([['directory' => '/', 'used' => 123, 'total' => 456]])],
        ]);
        DB::table('pulse_outgoing_requests')->insert([
            ['date' => '2000-01-02 03:04:05', 'uri' => 'GET http://example.com', 'duration' => 1234, 'slow' => true],
        ]);
        DB::table('pulse_slow_queries')->insert([
            ['date' => '2000-01-02 03:04:05', 'sql' => 'select * from `users`', 'location' => 'app/Foo.php:123', 'duration' => 1234],
        ]);
        DB::table('pulse_requests')->insert([
            ['date' => '2000-01-02 03:04:05', 'route' => 'GET /users', 'duration' => 500, 'slow' => false],
        ]);
    });
    expect(DB::table('pulse_cache_interactions')->count())->toBe(1);
    expect(DB::table('pulse_exceptions')->count())->toBe(1);
    expect(DB::table('pulse_jobs')->count())->toBe(1);
    expect(DB::table('pulse_system_stats')->count())->toBe(1);
    expect(DB::table('pulse_outgoing_requests')->count())->toBe(1);
    expect(DB::table('pulse_slow_queries')->count())->toBe(1);
    expect(DB::table('pulse_requests')->count())->toBe(1);

    Artisan::call('pulse:purge --exclude=SystemStats --exclude=Laravel\\\\Pulse\\\\Recorders\\\\SlowQueries');

    expect(DB::table('pulse_cache_interactions')->count())->toBe(0);
    expect(DB::table('pulse_exceptions')->count())->toBe(0);
    expect(DB::table('pulse_jobs')->count())->toBe(0);
    expect(DB::table('pulse_system_stats')->count())->toBe(1);
    expect(DB::table('pulse_outgoing_requests')->count())->toBe(0);
    expect(DB::table('pulse_slow_queries')->count())->toBe(1);
    expect(DB::table('pulse_requests')->count())->toBe(0);

    Pulse::ignore(fn () => Artisan::call('pulse:purge'));
});

it('can specify recorders', function () {
    Pulse::ignore(function () {
        Artisan::call('pulse:purge');
        DB::table('pulse_cache_interactions')->insert([
            ['date' => '2000-01-02 03:04:05', 'key' => 'foo', 'hit' => true],
        ]);
        DB::table('pulse_exceptions')->insert([
            ['date' => '2000-01-02 03:04:05', 'class' => 'RuntimeException', 'location' => 'app/Foo.php'],
        ]);
        DB::table('pulse_jobs')->insert([
            ['date' => '2000-01-02 03:04:05', 'job' => 'App\Jobs\MyJob', 'job_uuid' => Str::uuid(), 'attempt' => 1, 'connection' => 'database', 'queue' => 'default', 'queued_at' => '2000-01-02 03:04:05'],
        ]);
        DB::table('pulse_system_stats')->insert([
            ['date' => '2000-01-02 03:04:00', 'server' => 'Web 1', 'cpu_percent' => 12, 'memory_used' => 1234, 'memory_total' => 2468, 'storage' => json_encode([['directory' => '/', 'used' => 123, 'total' => 456]])],
        ]);
        DB::table('pulse_outgoing_requests')->insert([
            ['date' => '2000-01-02 03:04:05', 'uri' => 'GET http://example.com', 'duration' => 1234, 'slow' => true],
        ]);
        DB::table('pulse_slow_queries')->insert([
            ['date' => '2000-01-02 03:04:05', 'sql' => 'select * from `users`', 'location' => 'app/Foo.php:123', 'duration' => 1234],
        ]);
        DB::table('pulse_requests')->insert([
            ['date' => '2000-01-02 03:04:05', 'route' => 'GET /users', 'duration' => 500, 'slow' => false],
        ]);
    });
    expect(DB::table('pulse_cache_interactions')->count())->toBe(1);
    expect(DB::table('pulse_exceptions')->count())->toBe(1);
    expect(DB::table('pulse_jobs')->count())->toBe(1);
    expect(DB::table('pulse_system_stats')->count())->toBe(1);
    expect(DB::table('pulse_outgoing_requests')->count())->toBe(1);
    expect(DB::table('pulse_slow_queries')->count())->toBe(1);
    expect(DB::table('pulse_requests')->count())->toBe(1);

    Artisan::call('pulse:purge --only=SystemStats --only=Laravel\\\\Pulse\\\\Recorders\\\\SlowQueries');

    expect(DB::table('pulse_cache_interactions')->count())->toBe(1);
    expect(DB::table('pulse_exceptions')->count())->toBe(1);
    expect(DB::table('pulse_jobs')->count())->toBe(1);
    expect(DB::table('pulse_system_stats')->count())->toBe(0);
    expect(DB::table('pulse_outgoing_requests')->count())->toBe(1);
    expect(DB::table('pulse_slow_queries')->count())->toBe(0);
    expect(DB::table('pulse_requests')->count())->toBe(1);

    Pulse::ignore(fn () => Artisan::call('pulse:purge'));
});

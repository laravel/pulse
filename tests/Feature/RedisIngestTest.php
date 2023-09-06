<?php

use Illuminate\Support\Facades\App;
use Laravel\Pulse\Ingests\Redis;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Update;
use Tests\StorageFake;

it('can ingest an update with a closure', function () {
    $pulse = App::make(Pulse::class);
    $ingest = App::make(Redis::class);
    $storage = new StorageFake;
    $update = new Update('pulse_jobs', [
        'job_uuid' => 'unique-job-id',
    ], function () {
        return [
            'slowest' => 66,
        ];
    });

    $pulse->record($update);
    $pulse->store($ingest);
    $ingest->store($storage);

    expect($storage->stored)->toHaveCount(1);
    expect($storage->stored[0])->toBeInstanceOf(Update::class);
    expect($storage->stored[0])->not->toBe($update);
    expect($storage->stored[0]->conditions)->toBe([
        'job_uuid' => 'unique-job-id',
    ]);
    expect(($storage->stored[0]->attributes)())->toBe([
        'slowest' => 66,
    ]);
});

it('can ingest an update with a closure using external scope', function () {
    $pulse = App::make(Pulse::class);
    $ingest = App::make(Redis::class);
    $storage = new StorageFake;
    $duration = 66;
    $update = new Update('pulse_jobs', [
        'job_uuid' => 'unique-job-id',
    ], function () use ($duration) {
        return [
            'slowest' => $duration,
        ];
    });

    $pulse->record($update);
    $pulse->store($ingest);
    $ingest->store($storage);

    expect($storage->stored)->toHaveCount(1);
    expect($storage->stored[0])->toBeInstanceOf(Update::class);
    expect($storage->stored[0])->not->toBe($update);
    expect($storage->stored[0]->conditions)->toBe([
        'job_uuid' => 'unique-job-id',
    ]);
    expect(($storage->stored[0]->attributes)())->toBe([
        'slowest' => 66,
    ]);
});

it('can ingest an update with a short closure', function () {
    $pulse = App::make(Pulse::class);
    $ingest = App::make(Redis::class);
    $storage = new StorageFake;
    $update = new Update('pulse_jobs', [
        'job_uuid' => 'unique-job-id',
    ], fn () => [
        'slowest' => 66,
    ]);

    $pulse->record($update);
    $pulse->store($ingest);
    $ingest->store($storage);

    expect($storage->stored)->toHaveCount(1);
    expect($storage->stored[0])->toBeInstanceOf(Update::class);
    expect($storage->stored[0])->not->toBe($update);
    expect($storage->stored[0]->conditions)->toBe([
        'job_uuid' => 'unique-job-id',
    ]);
    expect(($storage->stored[0]->attributes)())->toBe([
        'slowest' => 66,
    ]);
});

it('can ingest an update with a short closure using external scope', function () {
    $pulse = App::make(Pulse::class);
    $ingest = App::make(Redis::class);
    $storage = new StorageFake;
    $duration = 66;
    $update = new Update('pulse_jobs', [
        'job_uuid' => 'unique-job-id',
    ], fn () => [
        'slowest' => $duration,
    ]);

    $pulse->record($update);
    $pulse->store($ingest);
    $ingest->store($storage);

    expect($storage->stored)->toHaveCount(1);
    expect($storage->stored[0])->toBeInstanceOf(Update::class);
    expect($storage->stored[0])->not->toBe($update);
    expect($storage->stored[0]->conditions)->toBe([
        'job_uuid' => 'unique-job-id',
    ]);
    expect(($storage->stored[0]->attributes)())->toBe([
        'slowest' => 66,
    ]);
});

it('can ingest an update array based attributes', function () {
    $pulse = App::make(Pulse::class);
    $ingest = App::make(Redis::class);
    $storage = new StorageFake;
    $duration = 66;
    $update = new Update('pulse_jobs', [
        'job_uuid' => 'unique-job-id',
    ], [
        'slowest' => $duration,
    ]);

    $pulse->record($update);
    $pulse->store($ingest);
    $ingest->store($storage);

    expect($storage->stored)->toHaveCount(1);
    expect($storage->stored[0])->toBeInstanceOf(Update::class);
    expect($storage->stored[0])->not->toBe($update);
    expect($storage->stored[0]->conditions)->toBe([
        'job_uuid' => 'unique-job-id',
    ]);
    expect($storage->stored[0]->attributes)->toBe([
        'slowest' => 66,
    ]);
});

<?php

use Illuminate\Support\Facades\App;
use Laravel\Pulse\Ingests\Redis;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Update;
use Tests\StorageFake;

it('can ingest an update with a closure with "use" variables', function () {
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
});

<?php

use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\SlowJobFinished;
use Laravel\Pulse\Storage\Database;

it('performs slow job updates', function () {
    $storage = App::make(Database::class);
    $update = new SlowJobFinished('job-uuid', 321);

    $storage->store(collect([
        new Entry('pulse_jobs', [
            'date' => now()->toDateTimeString(),
            'job' => 'MyJob',
            'job_uuid' => 'job-uuid',
            'user_id' => '55',
        ]),
    ]));

    $storage->store(collect([
        new SlowJobFinished('job-uuid', 456),
    ]));

    $jobs = DB::table('pulse_jobs')->get();
    $this->assertCount(1, $jobs);
    $this->assertSame(1, $jobs[0]->slow);
    $this->assertSame(456, $jobs[0]->slowest);
});

it('allows custom update handlers', function () {
    $captured = null;
    App::make(Database::class)->handleUpdateUsing(function (SlowJobFinished $update) use (&$captured) {
        $captured = $update;
    });

    $update = new SlowJobFinished('job-uuid', 321);
    App::make(Database::class)->store(collect([$update])); // resolve again to ensure that the handlers persist across different resolves from container.

    $this->assertSame($update, $captured);
});

<?php

use Illuminate\Support\Facades\App;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Value;
use Tests\StorageFake;

it('can filter records', function () {
    App::instance(Storage::class, $storage = new StorageFake);

    Pulse::filter(fn ($value) => $value::class === Entry::class && $value->key === 'keep' ||
        $value::class === Value::class && $value->key === 'keep');

    Pulse::record('foo', 'ignore', 0);
    Pulse::record('foo', 'keep', 0);
    Pulse::set('baz', 'keep', '');
    Pulse::set('baz', 'ignore', '');
    Pulse::store();

    expect($storage->stored)->toHaveCount(2);
    expect($storage->stored[0])->toBeInstanceOf(Entry::class);
    expect($storage->stored[0]->key)->toBe('keep');
    expect($storage->stored[1])->toBeInstanceOf(Value::class);
    expect($storage->stored[1]->key)->toBe('keep');
});

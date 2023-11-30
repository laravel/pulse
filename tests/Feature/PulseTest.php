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

it('can lazily capture entries', function () {
    App::instance(Storage::class, $storage = new StorageFake);

    Pulse::record('entry', 'eager');
    Pulse::lazy(function () {
        Pulse::record('entry', 'lazy');
        Pulse::set('value', 'lazy', '1');
    });
    Pulse::set('value', 'eager', '1');
    Pulse::store();

    expect($storage->stored)->toHaveCount(4);
    expect($storage->stored[0])->toBeInstanceOf(Entry::class);
    expect($storage->stored[0]->key)->toBe('eager');
    expect($storage->stored[1])->toBeInstanceOf(Value::class);
    expect($storage->stored[1]->key)->toBe('eager');
    expect($storage->stored[2])->toBeInstanceOf(Entry::class);
    expect($storage->stored[2]->key)->toBe('lazy');
    expect($storage->stored[3])->toBeInstanceOf(Value::class);
    expect($storage->stored[3]->key)->toBe('lazy');
});

it('can flush the queue', function () {
    App::instance(Storage::class, $storage = new StorageFake);

    Pulse::record('entry', 'eager');
    Pulse::lazy(function () {
        Pulse::record('entry', 'lazy');
    });
    Pulse::flush();

    expect(Pulse::store())->toBe(0);
});

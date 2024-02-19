<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Laravel\Pulse\Contracts\ResolvesUsers;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Value;
use Livewire\LivewireManager;
use Tests\StorageFake;
use Tests\User;

it('can filter records', function () {
    App::instance(Storage::class, $storage = new StorageFake);

    Pulse::filter(fn ($value) => $value::class === Entry::class && $value->key === 'keep' ||
        $value::class === Value::class && $value->key === 'keep');

    Pulse::record('foo', 'ignore', 0);
    Pulse::record('foo', 'keep', 0);
    Pulse::set('baz', 'keep', '');
    Pulse::set('baz', 'ignore', '');
    Pulse::ingest();

    expect($storage->stored)->toHaveCount(2);
    expect($storage->stored[0])->toBeInstanceOf(Entry::class);
    expect($storage->stored[0]->key)->toBe('keep');
    expect($storage->stored[1])->toBeInstanceOf(Value::class);
    expect($storage->stored[1]->key)->toBe('keep');
});

it('can trim records', function () {
    App::instance(Storage::class, $storage = new StorageFake);

    Pulse::record('foo', 'delete', 0, now()->subMonth());
    Pulse::record('foo', 'keep', 0);

    Pulse::ingest();

    expect($storage->stored)->toHaveCount(1);
});

it('can lazily capture entries', function () {
    App::instance(Storage::class, $storage = new StorageFake);

    Pulse::record('entry', 'eager');
    Pulse::lazy(function () {
        Pulse::record('entry', 'lazy');
        Pulse::set('value', 'lazy', '1');
    });
    Pulse::set('value', 'eager', '1');
    Pulse::ingest();

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

    expect(Pulse::ingest())->toBe(0);
});

it('resolves the authenticated user ID', function () {
    Auth::login(User::factory()->make(['id' => 123]));

    expect(Pulse::resolveAuthenticatedUserId())->toBe(123);
});

it('resolves users', function () {
    Pulse::stopRecording();
    User::factory()->create(['id' => 123, 'name' => 'Jess Archer', 'email' => 'jess@example.com']);
    User::factory()->create(['id' => 456, 'name' => 'Tim MacDonald', 'email' => 'tim@example.com']);

    $resolved = Pulse::resolveUsers(collect([123, 456]));

    expect($resolved->find(123))->toEqual((object) [
        'name' => 'Jess Archer',
        'extra' => 'jess@example.com',
        'avatar' => 'https://gravatar.com/avatar/d72141e224a6aa94fbd060f142e82aaadc05b1fed044017c230ab882523e2673?d=mp',
    ]);
    expect($resolved->find(456))->toEqual((object) [
        'name' => 'Tim MacDonald',
        'extra' => 'tim@example.com',
        'avatar' => 'https://gravatar.com/avatar/8f3b8f8fc3a3ffd7e7d42e0749da576587e4a3a5409c6416439099eeb5b8b67c?d=mp',
    ]);
});

it('can customize the user fields', function () {
    Pulse::stopRecording();
    User::factory()->create(['id' => 123, 'name' => 'Jess Archer', 'email' => 'jess@jessarcher.com']);

    Pulse::user(fn ($user) => [
        'name' => strtoupper($user->name),
        'extra' => strlen($user->name),
        'avatar' => 'https://example.com/avatar.png',
    ]);

    $user = Pulse::resolveUsers(collect([123]))->find(123);

    expect($user)->toEqual((object) [
        'name' => 'JESS ARCHER',
        'extra' => 11,
        'avatar' => 'https://example.com/avatar.png',
    ]);
});

it('maintains the users method for backwards compatibility', function () {
    Pulse::stopRecording();
    User::factory()->create(['id' => 123, 'name' => 'Jess Archer', 'email' => 'jess@jessarcher.com']);

    Pulse::users(function ($ids) {
        return User::findMany($ids)->map(fn ($user) => [
            'id' => $user->id,
            'name' => strtolower($user->name),
            'extra' => strlen($user->name),
            'avatar' => 'https://example.com/avatar.png',
        ]);
    });

    $user = Pulse::resolveUsers(collect([123]))->find(123);

    expect($user)->toEqual((object) [
        'name' => 'jess archer',
        'extra' => 11,
        'avatar' => 'https://example.com/avatar.png',
    ]);
});

it('can customize user resolving', function () {
    app()->singleton(ResolvesUsers::class, fn () => new class implements ResolvesUsers
    {
        public function key(Authenticatable $user): int|string|null
        {
            return json_encode(['123', '456']);
        }

        public function load(Collection $keys): self
        {
            return $this;
        }

        public function find(int|string|null $key): object
        {
            return (object) [
                'name' => 'Foo',
                'extra' => $key,
            ];
        }
    });

    Auth::login(User::factory()->make(['id' => 123]));

    expect(Pulse::resolveAuthenticatedUserId())->toBe('["123","456"]');

    $users = Pulse::resolveUsers(collect(['["123","456"]']));
    $user = $users->find('["123","456"]');
    expect($user)->toEqual((object) [
        'name' => 'Foo',
        'extra' => '["123","456"]',
    ]);
});

it('can limit the buffer size of entries', function () {
    Config::set('pulse.ingest.buffer', 4);

    Pulse::record('type', 'key');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::record('type', 'key');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::record('type', 'key');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::record('type', 'key');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::record('type', 'key');
    expect(Pulse::wantsIngesting())->toBeFalse();

    Pulse::set('type', 'key', 'value');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::set('type', 'key', 'value');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::set('type', 'key', 'value');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::set('type', 'key', 'value');
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::set('type', 'key', 'value');
    expect(Pulse::wantsIngesting())->toBeFalse();
});

it('resolves lazy entries when considering the buffer', function () {
    Config::set('pulse.ingest.buffer', 4);

    Pulse::lazy(fn () => Pulse::record('type', 'key'));
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::lazy(fn () => Pulse::set('type', 'key', 'value'));
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::lazy(fn () => Pulse::record('type', 'key'));
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::lazy(fn () => Pulse::set('type', 'key', 'value'));
    expect(Pulse::wantsIngesting())->toBeTrue();
    Pulse::lazy(fn () => Pulse::record('type', 'key'));
    expect(Pulse::wantsIngesting())->toBeFalse();
});

it('rescues exceptions that occur while filtering', function () {
    $handled = false;
    Pulse::handleExceptionsUsing(function () use (&$handled) {
        $handled = true;
    });

    Pulse::filter(function ($entry) {
        throw new RuntimeException('Whoops!');
    });
    Pulse::record('type', 'key');
    Pulse::ingest();

    expect($handled)->toBe(true);
});

it('strips arguments from persistent middleware', function () {
    App::forgetInstance(LivewireManager::class);
    Facade::clearResolvedInstance('livewire');
    Config::set('pulse.middleware', [MyTestMiddleware::class.':admin']);

    $persistentMiddleware = Livewire::getPersistentMiddleware();

    expect($persistentMiddleware)->toContain(MyTestMiddleware::class);
    expect($persistentMiddleware)->not->toContain(MyTestMiddleware::class.':admin');
});

class MyTestMiddleware
{
    //
}

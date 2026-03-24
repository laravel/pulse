<?php

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Ingests\RedisIngest;
use Laravel\Pulse\Support\RedisAdapter;
use Laravel\Pulse\Support\RedisServerException;
use Tests\StorageFake;

$drivers = ['predis', 'phpredis', 'relay'];

function prepareForDriver($driver)
{
    $extension = match ($driver) {
        'phpredis' => 'redis',
        default => $driver,
    };

    match ($extension) {
        'predis' => null,
        'redis', 'relay' => ! extension_loaded($extension)
            ? test()->markTestSkipped("PHP extension [{$extension}] missing for Redis driver [{$driver}].")
            : null,
    };

    // Relay version 0.8.0 introduced a breaking change that requires the port be an integer.
    if ($driver === 'relay') {
        Config::set('database.redis.default.port', (int) Config::get('database.redis.default.port'));
    }
}

beforeEach(function () {
    try {
        Process::timeout(1)->run('redis-cli -p '.Config::get('database.redis.default.port').' FLUSHALL')->throw();
    } catch (ProcessFailedException $e) {
        $this->markTestSkipped('Unable to run `redis-cli`');
    }
});

it('runs the same commands while ingesting entries', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);

    $commands = captureRedisCommands(fn () => App::make(RedisIngest::class)->ingest(collect([
        new Entry(timestamp: 1700752211, type: 'foo', key: 'bar', value: 123),
    ])));

    $prefix = Config::get('database.redis.options.prefix');

    // Find the XADD command and verify it targets the correct stream with serialized Entry data
    $xaddCommand = $commands->first(fn ($cmd) => str_starts_with($cmd, '"XADD"'));
    expect($xaddCommand)->not->toBeNull();
    expect($xaddCommand)->toContain('"XADD" "'.$prefix.'laravel:pulse:ingest" "*" "data"');

    // Verify the serialized data can be deserialized back to a matching Entry
    preg_match('/"data" "(.*)"$/', $xaddCommand, $matches);
    $serializedData = stripcslashes($matches[1]);
    $entry = unserialize($serializedData, ['allowed_classes' => [Entry::class]]);
    expect($entry)->toBeInstanceOf(Entry::class);
    expect($entry->timestamp)->toBe(1700752211);
    expect($entry->type)->toBe('foo');
    expect($entry->key)->toBe('bar');
    expect($entry->value)->toBe(123);
})->with($drivers);

it('keeps 7 days of data, by default, when trimming', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());

    $commands = captureRedisCommands(fn () => App::make(RedisIngest::class)->trim());

    $prefix = Config::get('database.redis.options.prefix');
    expect($commands)->toContain('"XTRIM" "'.$prefix.'laravel:pulse:ingest" "MINID" "~" "946177445000"');
})->with($drivers);

it('can configure days of data to keep when trimming', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());
    Config::set('pulse.ingest.trim.keep', '1 day');

    $commands = captureRedisCommands(fn () => App::make(RedisIngest::class)->trim());

    $prefix = Config::get('database.redis.options.prefix');
    expect($commands)->toContain('"XTRIM" "'.$prefix.'laravel:pulse:ingest" "MINID" "~" "946695845000"');
})->with($drivers);

it('can configure the number of entries to keep when trimming', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());
    Config::set('pulse.ingest.trim.keep', 54321);

    $commands = captureRedisCommands(fn () => App::make(RedisIngest::class)->trim());

    $prefix = Config::get('database.redis.options.prefix');
    expect($commands)->toContain('"XTRIM" "'.$prefix.'laravel:pulse:ingest" "MAXLEN" "~" "54321"');
})->with($drivers);

it('runs the same commands while storing', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    Config::set('pulse.ingest.redis.chunk', 567);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());
    $prefix = Config::get('database.redis.options.prefix');
    $ingest = App::make(RedisIngest::class);
    $ingest->ingest(collect([
        new Entry(timestamp: 1700752211, type: 'foo', key: 'bar', value: 123),
        new Entry(timestamp: 1700752211, type: 'foo', key: 'baz', value: 456),
    ]));
    $output = Process::timeout(1)
        ->run('redis-cli -p '.Config::get('database.redis.default.port').' XINFO STREAM '.$prefix.'laravel:pulse:ingest')
        ->throw()
        ->output();
    $lines = collect(explode("\n", $output))->map(fn ($line) => trim($line));
    $firstEntryKey = $lines->get($lines->search('first-entry') + 1);
    $lastEntryKey = $lines->get($lines->search('last-entry') + 1);

    $commands = captureRedisCommands(fn () => $ingest->digest(new StorageFake));

    expect($commands)->toContain('"XRANGE" "'.$prefix.'laravel:pulse:ingest" "-" "+" "COUNT" "567"');
    expect($commands)->toContain('"XDEL" "'.$prefix.'laravel:pulse:ingest" "'.$firstEntryKey.'" "'.$lastEntryKey.'"');
})->with($drivers);

it('has consistent return for xadd', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(Redis::connection(), App::make('config'));

    $result = $redis->xadd('stream-name', [
        'foo' => 1,
        'bar' => 2,
    ]);

    expect($result)->toBeString();
    $parts = explode('-', $result);
    expect($parts)->toHaveCount(2);
    expect($parts[0])->toEqualWithDelta(now()->getTimestampMs(), 50);
    expect($parts[1])->toBe('0');
})->with($drivers);

it('has consistent return for xrange', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(Redis::connection(), App::make('config'));
    $redis->xadd('stream-name', [
        'foo' => 1,
        'bar' => 2,
    ]);
    $redis->xadd('stream-name', [
        'foo' => 3,
        'bar' => 4,
    ]);

    $result = $redis->xrange('stream-name', '-', '+', 1000);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
    $values = [
        ['foo' => '1', 'bar' => '2'],
        ['foo' => '3', 'bar' => '4'],
    ];
    foreach ($result as $key => $value) {
        $parts = explode('-', $key);
        expect($parts)->toHaveCount(2);
        expect($parts[0])->toEqualWithDelta(now()->getTimestampMs(), 50);
        expect($parts[1])->toBeIn(['0', '1']);
        expect($value)->toBe(array_shift($values));
    }
})->with($drivers);

it('has consistent return for xtrim', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(Redis::connection(), App::make('config'));

    $redis->xadd('stream-name', [
        'foo' => 1,
        'bar' => 2,
    ]);
    $redis->xadd('stream-name', [
        'foo' => 3,
        'bar' => 4,
    ]);

    Sleep::for(5)->milliseconds();

    $lastKey = $redis->xadd('stream-name', [
        'foo' => 5,
        'bar' => 6,
    ]);

    $result = $redis->xtrim('stream-name', 'MINID', '=', Str::before($lastKey, '-'));

    expect($result)->toBe(2);
})->with($drivers);

it('throws exception on failure', function ($driver) {
    prepareForDriver($driver);

    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(Redis::connection(), App::make('config'));
    $prefix = Config::get('database.redis.options.prefix');

    try {
        $redis->xtrim('stream-name', 'FOO', 'a', 'xyz');
        test()->fail('Expected RedisServerException was not thrown.');
    } catch (RedisServerException $e) {
        expect($e->getMessage())->toBe('The Redis version does not support the command or some of its arguments [XTRIM '.$prefix.'stream-name FOO a xyz]. Redis error: [ERR syntax error].');
    }
})->with($drivers);

it('prepends the error message with the run command', function () {
    throw RedisServerException::whileRunningCommand('FOO BAR', 'Something happened');
})->throws(RedisServerException::class, 'Error running command [FOO BAR]. Redis error: [Something happened].');

<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Ingests\RedisIngest;
use Laravel\Pulse\Support\RedisAdapter;
use Laravel\Pulse\Support\RedisClientException;
use Tests\StorageFake;

beforeEach(fn () => Process::timeout(1)->run('redis-cli -p '.Config::get('database.redis.default.port').' FLUSHALL')->throw());

it('runs the same commands while ingesting entries', function ($driver) {
    Config::set('database.redis.client', $driver);

    $commands = captureRedisCommands(fn () => App::make(RedisIngest::class)->ingest(collect([
        new Entry(timestamp: 1700752211, type: 'foo', key: 'bar', value: 123),
    ])));

    expect($commands)->toContain('"XADD" "laravel_database_laravel:pulse:ingest" "*" "data" "O:19:\"Laravel\\\\Pulse\\\\Entry\\":6:{s:15:\"\x00*\x00aggregations\";a:0:{}s:14:\"\x00*\x00onlyBuckets\";b:0;s:9:\"timestamp\";i:1700752211;s:4:\"type\";s:3:\"foo\";s:3:\"key\";s:3:\"bar\";s:5:\"value\";i:123;}"');
})->with(['predis', 'phpredis']);

it('runs the same commands while triming the stream', function ($driver) {
    Config::set('database.redis.client', $driver);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());

    $commands = captureRedisCommands(fn () => App::make(RedisIngest::class)->trim());

    expect($commands)->toContain('"XTRIM" "laravel_database_laravel:pulse:ingest" "MINID" "~" "946177445000"');
})->with(['predis', 'phpredis']);

it('runs the same commands while storing', function ($driver) {
    Config::set('database.redis.client', $driver);
    Config::set('pulse.ingest.redis.chunk', 567);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());
    $ingest = App::make(RedisIngest::class);
    $ingest->ingest(collect([
        new Entry(timestamp: 1700752211, type: 'foo', key: 'bar', value: 123),
        new Entry(timestamp: 1700752211, type: 'foo', key: 'baz', value: 456),
    ]));
    $output = Process::timeout(1)
        ->run('redis-cli -p '.Config::get('database.redis.default.port').' XINFO STREAM laravel_database_laravel:pulse:ingest')
        ->throw()
        ->output();
    [$firstEntryKey, $lastEntryKey] = collect(explode("\n", $output))->only([17, 21])->values();

    $commands = captureRedisCommands(fn () => $ingest->store(new StorageFake()));

    expect($commands)->toContain('"XRANGE" "laravel_database_laravel:pulse:ingest" "-" "+" "COUNT" "567"');
    expect($commands)->toContain('"XDEL" "laravel_database_laravel:pulse:ingest" "'.$firstEntryKey.'" "'.$lastEntryKey.'"');
})->with(['predis', 'phpredis']);

it('has consistent return for xadd', function ($driver) {
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
})->with(['predis', 'phpredis']);

it('has consistent return for xrange', function ($driver) {
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
})->with(['predis', 'phpredis']);

it('has consistent return for xtrim', function ($driver) {
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
})->with(['predis', 'phpredis']);

it('throws exception on failure', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(Redis::connection(), App::make('config'));

    $redis->xtrim('stream-name', 'FOO', 'a', 'xyz');
})->with(['predis', 'phpredis'])->throws(RedisClientException::class, 'ERR syntax error');

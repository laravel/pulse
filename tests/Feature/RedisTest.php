<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Ingests\RedisIngest;
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

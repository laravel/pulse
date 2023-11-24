<?php

use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis as FacadesRedis;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Ingests\Redis;
use Laravel\Pulse\Redis as RedisAdapter;

beforeEach(fn () => Process::timeout(1)->run('redis-cli -p '.config('database.redis.default.port').' FLUSHALL')->throw());

it('runs the same commands while ingesting entries', function ($driver) {
    Config::set('database.redis.client', $driver);

    $commands = captureRedisCommands(fn () => App::make(Redis::class)->ingest(collect([
        new Entry(timestamp: 1700752211, type: 'foo', key: 'bar', value: 123),
    ])));

    expect($commands)->toContain('"XADD" "laravel_database_laravel:pulse:entries" "*" "data" "O:19:\"Laravel\\\\Pulse\\\\Entry\\":5:{s:15:\"\x00*\x00aggregations\";a:0:{}s:9:\"timestamp\";i:1700752211;s:4:\"type\";s:3:\"foo\";s:3:\"key\";s:3:\"bar\";s:5:\"value\";i:123;}"');
})->with(['predis', 'phpredis']);

it('runs the same commands while triming the stream', function ($driver) {
    Config::set('database.redis.client', $driver);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());

    $commands = captureRedisCommands(fn () => App::make(Redis::class)->trim());

    expect($commands)->toContain('"XTRIM" "laravel_database_laravel:pulse:entries" "MINID" "~" "946177445000"');
})->with(['predis', 'phpredis']);

it('runs the same commands while storing', function ($driver) {
    Config::set('database.redis.client', $driver);
    Config::set('pulse.ingest.redis.chunk', 567);
    Date::setTestNow(Date::parse('2000-01-02 03:04:05')->startOfSecond());
    $ingest = App::make(Redis::class);
    $ingest->ingest(collect([
        new Entry(timestamp: 1700752211, type: 'foo', key: 'bar', value: 123),
        new Entry(timestamp: 1700752211, type: 'foo', key: 'baz', value: 456),
    ]));
    $output = Process::timeout(1)
        ->run('redis-cli -p '.config('database.redis.default.port').' XINFO STREAM laravel_database_laravel:pulse:entries')
        ->throw()
        ->output();
    [$firstEntryKey, $lastEntryKey] = collect(explode("\n", $output))->only([17, 21])->values();

    $commands = captureRedisCommands(fn () => $ingest->store(new NullStorage));

    expect($commands)->toContain('"XRANGE" "laravel_database_laravel:pulse:entries" "-" "+" "COUNT" "567"');
    expect($commands)->toContain('"XDEL" "laravel_database_laravel:pulse:entries" "'.$firstEntryKey.'" "'.$lastEntryKey.'"');
})->with(['predis', 'phpredis']);

it('runs the same zincrby command', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(FacadesRedis::connection(), App::make('config'));

    $commands = captureRedisCommands(fn () => $redis->zincrby('MYKEY', '55', 'my-member'));
    expect($commands)->toContain('"ZINCRBY" "laravel_database_MYKEY" "55" "my-member"');

    $commands = captureRedisCommands(fn () => $redis->zincrby('MYKEY', '-55', 'my-member'));
    expect($commands)->toContain('"ZINCRBY" "laravel_database_MYKEY" "-55" "my-member"');

    $commands = captureRedisCommands(fn () => $redis->pipeline(fn ($r) => $r->zincrby('MYKEY', '-55', 'my-member')));
    expect($commands)->toContain('"ZINCRBY" "laravel_database_MYKEY" "-55" "my-member"');
})->with(['predis', 'phpredis']);

it('runs the same zrange command', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(FacadesRedis::connection(), App::make('config'));

    $commands = captureRedisCommands(fn () => $redis->zrange('MYKEY', 2, 3, reversed: false, withScores: false));
    expect($commands)->toContain('"ZRANGE" "laravel_database_MYKEY" "2" "3"');

    $commands = captureRedisCommands(fn () => $redis->zrange('MYKEY', 2, 3, reversed: true, withScores: false));
    expect($commands)->toContain('"ZRANGE" "laravel_database_MYKEY" "2" "3" "REV"');

    $commands = captureRedisCommands(fn () => $redis->zrange('MYKEY', 2, 3, reversed: false, withScores: true));
    expect($commands)->toContain('"ZRANGE" "laravel_database_MYKEY" "2" "3" "WITHSCORES"');

    $commands = captureRedisCommands(fn () => $redis->zrange('MYKEY', 2, 3, reversed: true, withScores: true));
    expect($commands)->toContain('"ZRANGE" "laravel_database_MYKEY" "2" "3" "REV" "WITHSCORES"');

    $commands = captureRedisCommands(fn () => $redis->pipeline(fn ($r) => $r->zrange('MYKEY', 2, 3, reversed: true, withScores: true)));
    expect($commands)->toContain('"ZRANGE" "laravel_database_MYKEY" "2" "3" "REV" "WITHSCORES"');
})->with(['predis', 'phpredis']);

it('runs the same get command', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(FacadesRedis::connection(), App::make('config'));

    $commands = captureRedisCommands(fn () => $redis->get('MYKEY'));
    expect($commands)->toContain('"GET" "laravel_database_MYKEY"');

    $commands = captureRedisCommands(fn () => $redis->pipeline(fn ($r) => $redis->get('MYKEY')));
    expect($commands)->toContain('"GET" "laravel_database_MYKEY"');
})->with(['predis', 'phpredis']);

it('runs the same set command', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(FacadesRedis::connection(), App::make('config'));

    $commands = captureRedisCommands(fn () => $redis->set('MYKEY', 'myvalue', CarbonInterval::seconds(5)));
    expect($commands)->toContain('"SET" "laravel_database_MYKEY" "myvalue" "PX" "5000"');

    $commands = captureRedisCommands(fn () => $redis->pipeline(fn ($r) => $r->set('MYKEY', 'myvalue', CarbonInterval::seconds(5))));
    expect($commands)->toContain('"SET" "laravel_database_MYKEY" "myvalue" "PX" "5000"');
})->with(['predis', 'phpredis']);

it('runs the same del command', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(FacadesRedis::connection(), App::make('config'));

    $commands = captureRedisCommands(fn () => $redis->del(['MYKEY', 'MYOTHERKEY']));
    expect($commands)->toContain('"DEL" "laravel_database_MYKEY" "laravel_database_MYOTHERKEY"');

    $commands = captureRedisCommands(fn () => $redis->pipeline(fn ($r) => $r->del(['MYKEY', 'MYOTHERKEY'])));
    expect($commands)->toContain('"DEL" "laravel_database_MYKEY" "laravel_database_MYOTHERKEY"');
})->with(['predis', 'phpredis']);

it('runs the same expire command', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(FacadesRedis::connection(), App::make('config'));

    $commands = captureRedisCommands(fn () => $redis->expire('MYKEY', CarbonInterval::day()));
    expect($commands)->toContain('"EXPIRE" "laravel_database_MYKEY" "86400"');

    $commands = captureRedisCommands(fn () => $redis->pipeline(fn ($r) => $r->expire('MYKEY', CarbonInterval::day())));
    expect($commands)->toContain('"EXPIRE" "laravel_database_MYKEY" "86400"');
})->with(['predis', 'phpredis']);

it('runs the same remrangebyscore command', function ($driver) {
    Config::set('database.redis.client', $driver);
    $redis = new RedisAdapter(FacadesRedis::connection(), App::make('config'));

    $commands = captureRedisCommands(fn () => $redis->zremrangebyscore('MYKEY', 0, 10));
    expect($commands)->toContain('"ZREMRANGEBYSCORE" "laravel_database_MYKEY" "0" "10"');

    $commands = captureRedisCommands(fn () => $redis->pipeline(fn ($r) => $r->zremrangebyscore('MYKEY', 0, 10)));
    expect($commands)->toContain('"ZREMRANGEBYSCORE" "laravel_database_MYKEY" "0" "10"');
})->with(['predis', 'phpredis']);

class NullStorage implements Storage
{
    public function store(Collection $items): void
    {
        //
    }

    public function trim(): void
    {
        //
    }

    public function purge(Collection $tables): void
    {
        //
    }
}

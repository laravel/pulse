<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Ingests\Redis;

beforeEach(fn () => Process::timeout(1)->run('redis-cli -p '.config('database.redis.default.port').' DEL laravel_database_laravel:pulse:entries')->throw());

it('runs the same commands while ingesting entries', function ($driver) {
    Config::set('database.redis.client', $driver);

    $commands = captureRedisCommands(fn () => App::make(Redis::class)->ingest(collect([
        new Entry('pulse_table', ['entry' => 'data']),
    ])));

    expect($commands)->toContain('"XADD" "laravel_database_laravel:pulse:entries" "*" "data" "O:19:\"Laravel\\\\Pulse\\\\Entry\\":2:{s:5:\"table\";s:11:\"pulse_table\";s:10:\"attributes\";a:1:{s:5:\"entry\";s:4:\"data\";}}"');
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
        new Entry('pulse_table', ['entry' => 'data']),
        new Entry('pulse_table', ['another' => 'one']),
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

class NullStorage implements Storage
{
    public function store(Collection $items): void
    {
        //
    }

    public function trim(Collection $tables): void
    {
        //
    }

    public function purge(Collection $tables): void
    {
        //
    }
}

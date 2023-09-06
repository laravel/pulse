<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Pulse;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:work')]
class WorkCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:work';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Process the data from the stream.';

    /**
     * Handle the command.
     */
    public function handle(
        Pulse $pulse,
        Ingest $ingest,
        Storage $storage,
        CacheManager $cache,
    ): int {
        $lastRestart = $cache->get('laravel:pulse:restart');

        $lastTrimmedStorageAt = (new CarbonImmutable)->startOfMinute();

        while (true) {
            $now = new CarbonImmutable;

            if ($lastRestart !== $cache->get('laravel:pulse:restart')) {
                return self::SUCCESS;
            }

            if ($now->subMinute()->greaterThan($lastTrimmedStorageAt)) {
                $storage->trim($pulse->tables());

                $lastTrimmedStorageAt = $now;
            }

            $processed = $ingest->store($storage);

            if ($processed === 0) {
                Sleep::for(1)->second();
            }
        }
    }
}

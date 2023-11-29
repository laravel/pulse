<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Support\CacheStoreResolver;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'pulse:work')]
class WorkCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:work {--stop-when-empty : Stop when the stream is empty}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Process incoming Pulse data from the ingest stream';

    /**
     * Handle the command.
     */
    public function handle(
        Ingest $ingest,
        Storage $storage,
        CacheStoreResolver $cache,
    ): int {
        $lastRestart = $cache->store()->get('laravel:pulse:restart');

        $lastTrimmedStorageAt = CarbonImmutable::now()->startOfMinute();

        while (true) {
            $now = CarbonImmutable::now();

            if ($lastRestart !== $cache->store()->get('laravel:pulse:restart')) {
                return self::SUCCESS;
            }

            $ingest->store($storage);

            // TODO now we have our bucket strategy, how often should we trim
            // to keep the smallest amount of data possible.
            if ($now->subMinutes(10)->greaterThan($lastTrimmedStorageAt)) {
                $storage->trim();

                $lastTrimmedStorageAt = $now;
            }

            if ($this->option('stop-when-empty')) {
                return self::SUCCESS;
            }

            Sleep::for(1)->second();
        }
    }
}

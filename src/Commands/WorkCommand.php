<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Queries\Usage;
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
    public $signature = 'pulse:work';

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
        Pulse $pulse,
        Ingest $ingest,
        Storage $storage,
        CacheManager $cache,
        Usage $usage,
    ): int {
        $lastRestart = $cache->get('laravel:pulse:restart');

        $lastWarmedAt = with($cache->get('laravel:pulse:work:warmed_at'), fn (?int $timestamp) => $timestamp === null
            ? null
            : CarbonImmutable::createFromTimestamp($timestamp));

        $lastTrimmedStorageAt = (new CarbonImmutable)->startOfMinute();

        while (true) {
            $now = new CarbonImmutable;

            if ($lastRestart !== $cache->get('laravel:pulse:restart')) {
                $this->info('Pulse restart signal detected. Goodbye.', 'v');

                return self::SUCCESS;
            }

            $this->info('Storing data.', 'v');

            $count = $ingest->store($storage);

            $this->info("Stored [$count] entries.", 'v');

            if ($lastWarmedAt === null || $now->subSeconds(10)->greaterThan($lastWarmedAt)) {
                $this->info('Warming queries.', 'v');

                $usage->warm($now, $lastWarmedAt);

                $cache->forever('laravel:pulse:work:warmed_at', $now->timestamp);

                $lastWarmedAt = $now;

                $this->info('Queries warmed.', 'v');
            }

            if ($now->subHour()->greaterThan($lastTrimmedStorageAt)) {
                $this->info('Trimming tables.', 'v');

                $storage->trim($pulse->tables());

                $lastTrimmedStorageAt = $now;

                $this->info('Tables trimmed.', 'v');
            }

            Sleep::for(1)->second();
        }
    }
}

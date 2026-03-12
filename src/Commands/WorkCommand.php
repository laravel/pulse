<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Support\CacheStoreResolver;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Telescope;
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
        Pulse $pulse,
        CacheStoreResolver $cache,
    ): int {
        $lastRestart = $cache->store()->get('laravel:pulse:restart');

        $lastTrimmedStorageAt = CarbonImmutable::now()->startOfMinute();

        while (true) {
            $now = CarbonImmutable::now();

            if ($lastRestart !== $cache->store()->get('laravel:pulse:restart')) {
                return self::SUCCESS;
            }

            $pulse->digest();

            if ($now->subMinutes(10)->greaterThan($lastTrimmedStorageAt)) {
                $pulse->trim();

                $lastTrimmedStorageAt = $now;
            }

            $this->ensureTelescopeEntriesAreCollected();

            if ($this->option('stop-when-empty')) {
                return self::SUCCESS;
            }

            Sleep::for(1)->second();
        }
    }

    /**
     * Schedule Telescope to store entries if enabled.
     */
    protected function ensureTelescopeEntriesAreCollected(): void
    {
        if ($this->laravel->bound(EntriesRepository::class)) {
            Telescope::store($this->laravel->make(EntriesRepository::class));
        }
    }
}

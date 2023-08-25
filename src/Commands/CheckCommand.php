<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Events\Beat;
use Laravel\Pulse\Pulse;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:check')]
class CheckCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:check';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Take a snapshot of the current server\'s pulse';

    /**
     * Handle the command.
     */
    public function handle(
        Pulse $pulse,
        Ingest $ingest,
        CacheManager $cache,
        Dispatcher $event,
    ): int {
        $lastRestart = $cache->get('laravel:pulse:restart');

        // TODO: configure?
        $interval = CarbonInterval::seconds(5);

        $lastSnapshotAt = (new CarbonImmutable)->floorSeconds((int) $interval->totalSeconds);

        while (true) {
            $now = new CarbonImmutable();

            if ($cache->get('laravel:pulse:restart') !== $lastRestart) {
                return self::SUCCESS;
            }

            if ($now->subSeconds((int) $interval->totalSeconds)->lessThan($lastSnapshotAt)) {
                Sleep::for(1)->second();

                continue;
            }

            $lastSnapshotAt = $now->floorSeconds((int) $interval->totalSeconds);

            $event->dispatch(new Beat($lastSnapshotAt, $interval));

            $pulse->store($ingest);
        }
    }
}

<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Events\IsolatedBeat;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
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
        CacheManager $cache,
        Dispatcher $event,
    ): int {
        $lastRestart = $cache->get('laravel:pulse:restart');

        $interval = CarbonInterval::seconds(5);

        $lastSnapshotAt = CarbonImmutable::now()->floorSeconds((int) $interval->totalSeconds);

        while (true) {
            $now = CarbonImmutable::now();

            if ($now->subSeconds((int) $interval->totalSeconds)->lessThan($lastSnapshotAt)) {
                Sleep::for(500)->milliseconds();

                continue;
            }

            if ($lastRestart !== $cache->get('laravel:pulse:restart')) {
                return self::SUCCESS;
            }

            $lastSnapshotAt = $now->floorSeconds((int) $interval->totalSeconds);

            $event->dispatch(new SharedBeat($lastSnapshotAt, $interval));

            if ($cache->lock("laravel:pulse:check:{$lastSnapshotAt->getTimestamp()}", (int) $interval->totalSeconds)->get()) {
                $event->dispatch(new IsolatedBeat($lastSnapshotAt, $interval));
            }

            $pulse->store();
        }
    }
}

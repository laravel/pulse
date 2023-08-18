<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Laravel\Pulse\Checks\QueueSize;
use Laravel\Pulse\Checks\SystemStats;
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
     * The interval, in seconds, to check for new stats.
     */
    protected int $interval = 15;

    /**
     * Handle the command.
     */
    public function handle(
        Pulse $pulse,
        SystemStats $systemStats,
        QueueSize $queueSize,
        CacheManager $cache,
    ): int {
        $lastRestart = $cache->get('illuminate:pulse:restart');

        $lastSnapshotAt = (new CarbonImmutable)->floorSeconds($this->interval);

        while (true) {
            $now = new CarbonImmutable();

            if ($cache->get('illuminate:pulse:restart') !== $lastRestart) {
                return self::SUCCESS;
            }

            if ($now->subSeconds($this->interval)->lessThan($lastSnapshotAt)) {
                sleep(1);

                continue;
            }

            $lastSnapshotAt = $now->floorSeconds($this->interval);

            /*
             * Collect server stats.
             */

            $pulse->record($entry = $systemStats($lastSnapshotAt));

            $this->line('<fg=gray>[system stats]</> '.json_encode($entry->attributes));

            /*
             * Collect queue sizes.
             */

            if ($cache->lock("illuminate:pulse:check-queue-sizes:{$lastSnapshotAt->timestamp}", $this->interval)->get()) {
                $entries = $queueSize($lastSnapshotAt)->each(fn ($entry) => $pulse->record($entry));

                $this->line('<fg=gray>[queue sizes]</> '.$entries->pluck('attributes')->toJson());
            }

            $pulse->store();
        }
    }
}

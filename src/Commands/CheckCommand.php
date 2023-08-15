<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Checks\QueueSize;
use Laravel\Pulse\Checks\SystemStats;
use Laravel\Pulse\Facades\Pulse;
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
        SystemStats $systemStats,
        QueueSize $queueSize,
    ): int {
        $lastRestart = Cache::get('illuminate:pulse:restart');

        $lastSnapshotAt = (new CarbonImmutable)->floorSeconds($this->interval);

        while (true) {
            $now = new CarbonImmutable();

            if (Cache::get('illuminate:pulse:restart') !== $lastRestart) {
                return self::SUCCESS;
            }

            if ($now->subSeconds($this->interval)->lessThan($lastSnapshotAt)) {
                Sleep::for(1)->second();

                continue;
            }

            $lastSnapshotAt = $now->floorSeconds($this->interval);

            /*
             * Collect server stats.
             */

            Pulse::record($entry = $systemStats($lastSnapshotAt));

            $this->line('<fg=gray>[system stats]</> '.json_encode($entry->attributes));

            /*
             * Collect queue sizes.
             */

            if (Cache::lock("illuminate:pulse:check-queue-sizes:{$lastSnapshotAt->timestamp}", $this->interval)->get()) {
                $entries = $queueSize($lastSnapshotAt)->each(fn ($entry) => Pulse::record($entry));

                $this->line('<fg=gray>[queue sizes]</> '.$entries->pluck('attributes')->toJson());
            }

            Pulse::store();
        }
    }
}

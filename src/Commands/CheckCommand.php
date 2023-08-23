<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
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
     *
     * @param  \Illuminate\Support\Collection<int, (callable(\Carbon\CarbonImmutable, \Carbon\CarbonInterval): (\Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update|iterable<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>))>  $checks
     */
    public function handle(
        Pulse $pulse,
        CacheManager $cache,
        Collection $checks,
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

            $checks->map(fn (callable $check) => $check($lastSnapshotAt, $interval))
                ->filter()
                ->flatten()
                ->each($pulse->record(...));

            $pulse->store();
        }
    }
}

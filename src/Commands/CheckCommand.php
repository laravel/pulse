<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Sleep;
use Laravel\Pulse\Events\IsolatedBeat;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Support\CacheStoreResolver;
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
    public $signature = 'pulse:check
                        {--once : Take a snapshot once and exit}';

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
        CacheStoreResolver $cache,
        Dispatcher $event,
    ): int {
        if ($this->option('once')) {
            $this->check($pulse, $cache, $event);

            return self::SUCCESS;
        }

        $lastRestart = $cache->store()->get('laravel:pulse:restart');

        $interval = CarbonInterval::seconds(5);

        $lastSnapshotAt = CarbonImmutable::now()->floorSeconds((int) $interval->totalSeconds);

        while (true) {
            $now = CarbonImmutable::now();

            if ($now->subSeconds((int) $interval->totalSeconds)->lessThan($lastSnapshotAt)) {
                Sleep::for(500)->milliseconds();

                continue;
            }

            if ($lastRestart !== $cache->store()->get('laravel:pulse:restart')) {
                return self::SUCCESS;
            }

            $this->check($pulse, $cache, $event, $now, $interval);
        }
    }

    /**
     * Check the current server's pulse.
     */
    protected function check(
        Pulse $pulse,
        CacheStoreResolver $cache,
        Dispatcher $event,
        ?CarbonImmutable $now = null,
        ?CarbonInterval $interval = null
    ): void {
        $now ??= CarbonImmutable::now();

        $lastSnapshotAt = $interval ? $now->floorSeconds((int) $interval->totalSeconds) : $now;

        $event->dispatch(new SharedBeat($lastSnapshotAt, $interval));

        if (
            ($lockProvider ??= $cache->store()->getStore()) instanceof LockProvider &&
            $lockProvider->lock("laravel:pulse:check:{$lastSnapshotAt->getTimestamp()}", (int) $interval->totalSeconds)->get()
        ) {
            $event->dispatch(new IsolatedBeat($lastSnapshotAt, $interval));
        }

        $pulse->store();
    }
}

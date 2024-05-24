<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Env;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
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
    public $signature = 'pulse:check {--once : Take a single snapshot}';

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
        $isVapor = (bool) Env::get('VAPOR_SSM_PATH');

        $instance = $isVapor ? 'vapor' : Str::random();

        $lastRestart = $cache->store()->get('laravel:pulse:restart');

        $lock = ($store = $cache->store()->getStore()) instanceof LockProvider
            ? $store->lock('laravel:pulse:check', 1)
            : null;

        while (true) {
            if ($lastRestart !== $cache->store()->get('laravel:pulse:restart')) {
                return self::SUCCESS;
            }

            $now = CarbonImmutable::now();

            if ($lock?->get()) {
                $event->dispatch(new IsolatedBeat($now));
            }

            $event->dispatch(new SharedBeat($now, $instance));

            $pulse->ingest();

            if ($isVapor || $this->option('once')) {
                return self::SUCCESS;
            }

            Sleep::until($now->addSecond());
        }
    }
}

<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\InteractsWithTime;
use Laravel\Pulse\Support\CacheStoreResolver;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'pulse:restart')]
class RestartCommand extends Command
{
    use InteractsWithTime;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:restart';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Restart any running "work" and "check" commands';

    /**
     * Handle the command.
     */
    public function handle(CacheStoreResolver $cache): void
    {
        $cache->store()->forever('laravel:pulse:restart', $this->currentTime());

        $this->components->info('Broadcasting pulse restart signal.');
    }
}

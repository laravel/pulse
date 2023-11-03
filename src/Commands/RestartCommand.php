<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\InteractsWithTime;
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
    public function handle(CacheManager $cache): void
    {
        $cache->forever('laravel:pulse:restart', $this->currentTime());

        $this->components->info('Broadcasting pulse restart signal.');
    }
}

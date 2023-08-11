<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\InteractsWithTime;
use Symfony\Component\Console\Attribute\AsCommand;

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
    public $description = 'Restart running pulse work and check commands';

    /**
     * Handle the command.
     */
    public function handle(): void
    {
        Cache::forever('illuminate:pulse:restart', $this->currentTime());

        $this->components->info('Broadcasting pulse restart signal.');
    }
}

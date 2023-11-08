<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\DatabaseManager;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Queries\Concerns\InteractsWithConnection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:purge')]
class PurgeCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The database mananger.
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $db;

    /**
     * The config repository.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:purge {--force : Force the operation to run when in production}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Purge Pulse data';

    /**
     * Handle the command.
     */
    public function handle(
        Pulse $pulse,
        Storage $storage,
    ): int {
        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        $storage->purge($pulse->tables());

        $this->components->info("Tables purged.");

        return Command::SUCCESS;
    }
}

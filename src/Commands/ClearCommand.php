<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\DatabaseManager;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Queries\Concerns\InteractsWithConnection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:clear')]
class ClearCommand extends Command
{
    use ConfirmableTrait, InteractsWithConnection;

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
    public $signature = 'pulse:clear {--force : Force the operation to run when in production}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Clear Pulse data';

    /**
     * Handle the command.
     */
    public function handle(
        Pulse $pulse,
        Repository $config,
        DatabaseManager $db,
    ): int {
        $this->db = $db;
        $this->config = $config;

        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        $pulse->tables()->each(function ($table) use ($db) {
            $this->info("Clearing {$table}...");

            $this->connection()->table($table)->truncate();
        });

        return Command::SUCCESS;
    }
}

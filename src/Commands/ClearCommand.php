<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Queries\Concerns\InteractsWithConnection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:clear')]
class ClearCommand extends Command
{
    use ConfirmableTrait;
    use InteractsWithConnection;

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
     * Create a new command instance.
     */
    public function __construct(
        protected Repository $config,
        protected DatabaseManager $db
    ) {
        parent::__construct();
    }

    /**
     * Handle the command.
     */
    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        collect(array_keys(Config::get('pulse.recorders')))
            ->map(fn ($recorder) => app($recorder)->table) // @phpstan-ignore argument.type
            ->each(function ($table) {
                $this->info("Clearing {$table}...");

                $this->connection()->query()
                    ->from($table)
                    ->truncate();
            });

        return Command::SUCCESS;
    }
}

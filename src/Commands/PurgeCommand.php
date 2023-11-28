<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Laravel\Pulse\Contracts\Storage;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'pulse:purge')]
class PurgeCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:purge {--type=* : Only clear the specified type(s)}
                                     {--force : Force the operation to run when in production}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Purge Pulse data';

    /**
     * Handle the command.
     */
    public function handle(Storage $storage): int {
        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        if (count($this->option('type')) > 0) {
            $this->components->task(
                'Purging Pulse data for ['.implode(', ', $this->option('type')).']',
                fn () => $storage->purge($this->option('type'))
            );
        } else {
            $this->components->task(
                'Purging all Pulse data',
                fn () => $storage->purge()
            );
        }

        return Command::SUCCESS;
    }
}

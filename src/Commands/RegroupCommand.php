<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Laravel\Pulse\Contracts\Groupable;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Pulse;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:regroup')]
class RegroupCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:regroup {--force : Force the operation to run when in production}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Re-apply grouping for supporting recorders.';

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

        $pulse->recorders()
            ->filter(fn ($recorder) => $recorder instanceof Groupable)
            ->tap($storage->regroup(...));

        $this->components->info('Recorders regrouped.');

        return Command::SUCCESS;
    }
}

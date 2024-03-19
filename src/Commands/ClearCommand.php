<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Laravel\Pulse\Pulse;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'pulse:clear', aliases: ['pulse:purge'])]
class ClearCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:clear {--type=* : Only clear the specified type(s)}
                                     {--force : Force the operation to run when in production}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Delete all Pulse data from storage';

    /**
     * The console command name aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['pulse:purge'];

    /**
     * Handle the command.
     */
    public function handle(Pulse $pulse): int
    {
        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        if (is_array($this->option('type')) && count($this->option('type')) > 0) {
            $this->components->task(
                'Purging Pulse data for ['.implode(', ', $this->option('type')).']',
                fn () => $pulse->purge($this->option('type'))
            );
        } else {
            $this->components->task(
                'Purging all Pulse data',
                fn () => $pulse->purge(),
            );
        }

        return Command::SUCCESS;
    }
}

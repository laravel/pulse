<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Contracts\Grouping;
use Laravel\Pulse\Support\DatabaseConnectionResolver;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
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
        DatabaseConnectionResolver $db,
        Repository $config,
    ): int
    {
        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        collect(array_keys($config->get('pulse.recorders')))
            ->filter(fn ($recorder) => (new ReflectionClass($recorder))->implementsInterface(Grouping::class)) // @phpstan-ignore argument.type
            ->map(fn ($recorder) => app($recorder)) // @phpstan-ignore argument.type
            ->each(function ($recorder) use ($db) {
                $this->info("Re-grouping {$recorder->table}...");

                $db->connection()
                    ->table($recorder->table)
                    ->select($recorder->groupColumn())
                    ->distinct()
                    ->pluck($recorder->groupColumn())
                    ->each(function ($value) use ($db, $recorder) {
                        $newValue = $recorder->group($value)();

                        if ($newValue === $value) {
                            return;
                        }

                        $this->info(" - [{$value}] => [{$newValue}]");

                        $db->connection()
                            ->table($recorder->table)
                            ->where($recorder->groupColumn(), $value)
                            ->update([
                                $recorder->groupColumn() => $newValue,
                            ]);
                    });
            });

        return Command::SUCCESS;
    }
}

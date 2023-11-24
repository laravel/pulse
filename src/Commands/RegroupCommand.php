<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Concerns\InteractsWithDatabaseConnection;
use Laravel\Pulse\Contracts\Grouping;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal
 */
#[AsCommand(name: 'pulse:regroup')]
class RegroupCommand extends Command
{
    use ConfirmableTrait, InteractsWithDatabaseConnection;

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
            ->filter(fn ($recorder) => (new ReflectionClass($recorder))->implementsInterface(Grouping::class)) // @phpstan-ignore argument.type
            ->map(fn ($recorder) => app($recorder)) // @phpstan-ignore argument.type
            ->each(function ($recorder) {
                $this->info("Re-grouping {$recorder->table}...");

                $this->db()->query()
                    ->from($recorder->table)
                    ->select($recorder->groupColumn())
                    ->distinct()
                    ->pluck($recorder->groupColumn())
                    ->each(function ($value) use ($recorder) {
                        $newValue = $recorder->group($value)();

                        if ($newValue === $value) {
                            return;
                        }

                        $this->info(" - [{$value}] => [{$newValue}]");

                        $this->db()->query()
                            ->from($recorder->table)
                            ->where($recorder->groupColumn(), $value)
                            ->update([
                                $recorder->groupColumn() => $newValue,
                            ]);
                    });
            });

        return Command::SUCCESS;
    }
}

<?php

namespace Laravel\Pulse\Commands;

use Exception;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Pulse;
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
    public $signature = 'pulse:purge {--exclude=* : Exclude recorders from being cleared}
                                     {--only=* : Only clear the specified recorders}
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
    public function handle(
        Pulse $pulse,
        Storage $storage,
    ): int {
        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        if (count($this->option('exclude')) > 0 && count($this->option('only')) > 0) { // @phpstan-ignore argument.type argument.type
            $this->error("You can't use both the --exclude and --only options together.");

            return Command::FAILURE;
        }

        $recorders = match(true) {
            count($this->option('exclude')) > 0 => $this->exclude($pulse->recorders(), $this->option('exclude')), // @phpstan-ignore argument.type argument.type
            count($this->option('only')) > 0 => $this->only($pulse->recorders(), $this->option('only')), // @phpstan-ignore argument.type argument.type
            default => $pulse->recorders(),
        };

        $tables = $recorders
            ->map(fn ($recorder) => $recorder->table ?? null)
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $storage->purge($tables);

        $this->components->info('Tables purged.');

        return Command::SUCCESS;
    }

    /**
     * Exclude recorders from the collection.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $recorders
     * @param  list<string>  $excludes
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function exclude(Collection $recorders, array $excludes): Collection
    {
        foreach ($excludes as $exclude) {
            $index = $recorders->search(function ($recorder) use ($exclude) {
                return get_class($recorder) === $exclude
                    || Str::afterLast(get_class($recorder), '\\') === $exclude;
            });

            if ($index === false) {
                throw new Exception("The recorder [{$exclude}] does not exist.");
            }

            $recorders->forget($index);
        }

        return $recorders;
    }

    /**
     * Only include the specified recorders in the list.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $recorders
     * @param  list<string>  $only
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function only(Collection $recorders, array $only): Collection
    {
        return collect($only)->map(function ($only) use ($recorders) {
            $recorder = $recorders->first(function ($recorder) use ($only) {
                return get_class($recorder) === $only
                    || Str::afterLast(get_class($recorder), '\\') === $only;
            });

            if ($recorder === null) {
                throw new Exception("The recorder [{$only}] does not exist.");
            }

            return $recorder;
        });
    }
}

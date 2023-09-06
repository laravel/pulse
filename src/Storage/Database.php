<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Closure;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ReflectsClosures;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\SlowJobFinished;
use Laravel\Pulse\Entries\Update;

class Database implements Storage
{
    use ReflectsClosures;

    /**
     * Additional storage update handlers.
     */
    protected array $updateHandlers = [];

    /**
     * Create a new Database storage instance.
     */
    public function __construct(
        protected DatabaseManager $manager,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Handle the update using the closure.
     *
     * @param  (callable(Update): void)  $callback
     */
    public function handleUpdateUsing($callback): self
    {
        foreach ($this->firstClosureParameterTypes(Closure::fromCallable($callback)) as $class) {
            $this->updateHandlers[$class] = $callback;
        }

        return $this;
    }

    /**
     * Store the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>  $items
     */
    public function store(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        [$inserts, $updates] = $items->partition(fn (Entry|Update $entry) => $entry instanceof Entry);

        $this->connection()->transaction(function () use ($inserts, $updates) {
            $inserts->groupBy('table')
                ->each(fn (Collection $rows, string $table) => $rows->chunk($this->config->get('pulse.storage.database.chunk'))
                    ->map(fn (Collection $inserts) => $inserts->pluck('attributes')->all())
                    ->each($this->connection()->table($table)->insert(...)));

            $this->perform($updates);
        });
    }

    /**
     * Perform the given updates.
     */
    protected function perform($updates)
    {
        $updates->each(function (Update $update) {
            if ($this->updateHandlers[$update::class] ?? false) {
                $this->updateHandlers[$update::class]($update);

                return;
            }

            if ($update instanceof SlowJobFinished) {
                $this->connection()->table($update->table())
                    ->where('job_uuid', $update->jobUuid)
                    ->update([
                        'slowest' => $this->connection()->raw("COALESCE(GREATEST(`slowest`,{$update->duration}),{$update->duration})"),
                        'slow' => $this->connection()->raw('`slow` + 1'),
                    ]);
            }
        });
    }

    /**
     * Trim the stored entries.
     */
    public function trim(Collection $tables): void
    {
        $tables->each(fn (string $table) => $this->connection()
            ->table($table)
            ->where('date', '<', (new CarbonImmutable)->subSeconds((int) $this->trimAfter()->totalSeconds)->toDateTimeString())
            ->delete());
    }

    /**
     * The interval to trim the storage to.
     */
    protected function trimAfter(): Interval
    {
        return new Interval($this->config->get('pulse.retain'));
    }

    /**
     * Get the database connection.
     */
    public function connection(): Connection
    {
        return $this->manager->connection(
            $this->config->get('pulse.storage.database.connection') ?? $this->config->get('database.default')
        );
    }
}

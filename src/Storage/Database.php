<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

class Database implements Storage
{
    /**
     * Create a new Database storage instance.
     */
    public function __construct(
        protected DatabaseConnectionResolver $db,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Store the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     */
    public function store(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        // TODO: Transactions!

        $entries
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->db->connection()
                ->table('pulse_entries')
                ->insert($chunk->map->attributes()->toArray()) // @phpstan-ignore method.notFound
            );

        $periods = collect([
            Interval::hour()->totalSeconds / 60,
            Interval::hours(6)->totalSeconds / 60,
            Interval::hours(24)->totalSeconds / 60,
            Interval::days(7)->totalSeconds / 60,
        ]);

        $entries->filter->isCount() // @phpstan-ignore method.notFound
            ->chunk((int) $this->config->get('pulse.storage.database.chunk') / $periods->count())
            ->each(fn ($chunk) => $this->upsertCount(
                $periods->flatMap(fn ($period) => $chunk->map->countAttributes($period))->all(), // @phpstan-ignore argument.templateType argument.templateType
                'pulse_aggregates'
            ));

        $entries->filter->isMax() // @phpstan-ignore method.notFound
            ->chunk((int) $this->config->get('pulse.storage.database.chunk') / $periods->count())
            ->each(fn ($chunk) => $this->upsertMax(
                $periods->flatMap(fn ($period) => $chunk->map->maxAttributes($period))->all(), // @phpstan-ignore argument.templateType argument.templateType
                'pulse_aggregates'
            ));

        $entries->filter->isAvg() // @phpstan-ignore method.notFound
            ->chunk((int) $this->config->get('pulse.storage.database.chunk') / $periods->count())
            ->each(fn ($chunk) => $this->upsertAvg(
                $periods->flatMap(fn ($period) => $chunk->map->avgAttributes($period))->all(), // @phpstan-ignore argument.templateType argument.templateType
                'pulse_aggregates'
            ));
    }

    /**
     * Trim the storage.
     */
    public function trim(): void
    {
        $now = CarbonImmutable::now();

        $this->db->connection()
            ->table('pulse_values')
            ->where('expires_at', '<=', $now->getTimestamp())
            ->delete();

        $this->db->connection()
            ->table('pulse_entries')
            ->where('timestamp', '<=', $now->subWeek()->getTimestamp())
            ->delete();

        $this->db->connection()
            ->table('pulse_aggregates')
            ->distinct()
            ->pluck('period')
            ->each(fn (int $period) => $this->db->connection()
                ->table('pulse_aggregates')
                ->where('period', $period)
                ->where('bucket', '<=', $now->subMinutes($period)->getTimestamp())
                ->delete());
    }

    /**
     * Purge the stored entries from the given tables.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tables
     */
    public function purge(Collection $tables): void
    {
        $tables->each(fn (string $table) => $this->db->connection()
            ->table($table)
            ->truncate());
    }

    /**
     * Insert new records or update the existing ones and increment the count.
     *
     * @param  list<\Laravel\Pulse\Entry>  $values
     */
    protected function upsertCount(array $values, string $table, string $valueColumn = 'value'): bool
    {
        $grammar = $this->db->connection()->getQueryGrammar();

        $sql = $grammar->compileInsert(
            $this->db->connection()->table($table),
            $values
        );

        $sql .= sprintf(' on duplicate key update %1$s = %1$s + values(%1$s)', $grammar->wrap($valueColumn));

        return $this->db->connection()->statement($sql, Arr::flatten($values, 1));
    }

    /**
     * Insert new records or update the existing ones and the maximum.
     *
     * @param  list<\Laravel\Pulse\Entry>  $values
     */
    protected function upsertMax(array $values, string $table, string $valueColumn = 'value'): bool
    {
        $grammar = $this->db->connection()->getQueryGrammar();

        $sql = $grammar->compileInsert(
            $this->db->connection()->table($table),
            $values
        );

        $sql .= sprintf(' on duplicate key update %1$s = greatest(%1$s, values(%1$s))', $grammar->wrap($valueColumn));

        return $this->db->connection()->statement($sql, Arr::flatten($values, 1));
    }

    /**
     * Insert new records or update the existing ones and the average.
     *
     * @param  list<\Laravel\Pulse\Entry>  $values
     */
    protected function upsertAvg(array $values, string $table, string $valueColumn = 'value', string $countColumn = 'count'): bool
    {
        $grammar = $this->db->connection()->getQueryGrammar();

        $sql = $grammar->compileInsert(
            $this->db->connection()->table($table),
            $values
        );

        $sql .= sprintf(' on duplicate key update %1$s = (%1$s * %2$s + values(%1$s)) / (%2$s + 1), %2$s = %2$s + 1', $grammar->wrap($valueColumn), $grammar->wrap($countColumn));

        return $this->db->connection()->statement($sql, Arr::flatten($values, 1));
    }
}

<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Support\DatabaseConnectionResolver;
use Laravel\Pulse\Value;

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
     * Store the items.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     */
    public function store(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        // TODO: Transactions!

        [$entries, $values] = $entries->partition(fn (Entry|Value $entry) => $entry instanceof Entry);

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

        $entries->filter->isSum() // @phpstan-ignore method.notFound
            ->chunk((int) $this->config->get('pulse.storage.database.chunk') / $periods->count())
            ->each(fn ($chunk) => $this->upsertSum(
                'pulse_aggregates',
                $periods->flatMap(fn ($period) => $chunk->map->aggregateAttributes($period, 'sum'))->all() // @phpstan-ignore argument.templateType argument.templateType
            ));

        $entries->filter->isMax() // @phpstan-ignore method.notFound
            ->chunk((int) $this->config->get('pulse.storage.database.chunk') / $periods->count())
            ->each(fn ($chunk) => $this->upsertMax(
                'pulse_aggregates',
                $periods->flatMap(fn ($period) => $chunk->map->aggregateAttributes($period, 'max'))->all() // @phpstan-ignore argument.templateType argument.templateType
            ));

        $entries->filter->isAvg() // @phpstan-ignore method.notFound
            ->chunk((int) $this->config->get('pulse.storage.database.chunk') / $periods->count())
            ->each(fn ($chunk) => $this->upsertAvg(
                'pulse_aggregates',
                $periods->flatMap(fn ($period) => $chunk->map->aggregateAttributes($period, 'avg'))->all() // @phpstan-ignore argument.templateType argument.templateType
            ));

        $values
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->db->connection()
                ->table('pulse_values')
                ->upsert(
                    $chunk->map->attributes()->toArray(), // @phpstan-ignore method.notFound
                    ['type', 'key'],
                    ['timestamp', 'value']
                )
            );
    }

    /**
     * Trim the storage.
     */
    public function trim(): void
    {
        $now = CarbonImmutable::now();

        $this->db->connection()
            ->table('pulse_values')
            ->where('timestamp', '<=', $now->subWeek()->getTimestamp())
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
     * Purge the storage.
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
     * Insert new records or update the existing ones and update the sum.
     *
     * @param  list<\Laravel\Pulse\Entry>  $values
     */
    protected function upsertSum(
        string $table,
        array $values,
        string $valueColumn = 'value',
        string $countColumn = 'count'
    ): bool {
        return $this->upsert(
            $table,
            $values,
            'on duplicate key update %1$s = %1$s + values(%1$s), %2$s = %2$s + 1',
            [$valueColumn, $countColumn]
        );
    }

    /**
     * Insert new records or update the existing ones and the maximum.
     *
     * @param  list<\Laravel\Pulse\Entry>  $values
     */
    protected function upsertMax(
        string $table,
        array $values,
        string $valueColumn = 'value',
        string $countColumn = 'count'
    ): bool {
        return $this->upsert(
            $table,
            $values,
            'on duplicate key update %1$s = greatest(%1$s, values(%1$s)), %2$s = %2$s + 1',
            [$valueColumn, $countColumn]
        );
    }

    /**
     * Insert new records or update the existing ones and the average.
     *
     * @param  list<\Laravel\Pulse\Entry>  $values
     */
    protected function upsertAvg(
        string $table,
        array $values,
        string $valueColumn = 'value',
        string $countColumn = 'count'
    ): bool {
        return $this->upsert(
            $table,
            $values,
            ' on duplicate key update %1$s = (%1$s * %2$s + values(%1$s)) / (%2$s + 1), %2$s = %2$s + 1',
            [$valueColumn, $countColumn]
        );
    }

    /**
     * Perform an "upsert" query with an "on duplicate key" clause.
     *
     * @param  list<\Laravel\Pulse\Entry>  $values
     */
    protected function upsert(
        string $table,
        array $values,
        string $onDuplicateKeyClause,
        array $onDuplicateKeyColumns = [],
    ): bool {
        $grammar = $this->db->connection()->getQueryGrammar();

        $sql = $grammar->compileInsert(
            $this->db->connection()->table($table),
            $values
        );

        $sql .= ' '.sprintf($onDuplicateKeyClause, ...$onDuplicateKeyColumns);

        return $this->db->connection()->statement($sql, Arr::flatten($values, 1));
    }
}

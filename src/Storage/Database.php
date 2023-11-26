<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
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
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Value>  $items
     */
    public function store(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        // TODO: Transactions!

        [$entries, $values] = $items->partition(fn (Entry|Value $entry) => $entry instanceof Entry);

        $entries
            ->reject->isBucketOnly()
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->db->connection()
                ->table('pulse_entries')
                ->insert($chunk->map->attributes()->toArray())
            );

        $periods = [
            Interval::hour()->totalSeconds / 60,
            Interval::hours(6)->totalSeconds / 60,
            Interval::hours(24)->totalSeconds / 60,
            Interval::days(7)->totalSeconds / 60,
        ];

        $this
            ->aggregateAttributes($entries->filter->isSum(), $periods, 'sum')
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->upsertSum('pulse_aggregates', $chunk->all()));

        $this
            ->aggregateAttributes($entries->filter->isMax(), $periods, 'max')
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->upsertMax('pulse_aggregates', $chunk->all()));

        $this
            ->aggregateAttributes($entries->filter->isAvg(), $periods, 'avg')
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->upsertAvg('pulse_aggregates', $chunk->all()));

        $values
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->db->connection()
                ->table('pulse_values')
                ->upsert(
                    $chunk->map->attributes()->toArray(),
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
     * @param  list<string>  $onDuplicateKeyColumns
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

    /**
     * Get the aggregate attributes for the collection.
     */
    protected function aggregateAttributes(Collection $entries, array $periods, string $aggregateSuffix): LazyCollection
    {
        return LazyCollection::make(function () use ($entries, $periods, $aggregateSuffix) {
            foreach ($entries as $entry) {
                foreach ($periods as $period) {
                    // Exclude entries that would be trimmed.
                    if ($entry->timestamp < now()->subMinutes($period)->getTimestamp()) {
                        continue;
                    }

                    yield $entry->aggregateAttributes($period, $aggregateSuffix);
                }
            }
        });
    }

    /**
     * Retrieve values for the given type.
     */
    public function values(string $type, array $keys = null): Collection
    {
        return $this->db->connection()
            ->table('pulse_values')
            ->where('type', $type)
            ->when($keys, fn ($query) => $query->whereIn('key', $keys))
            ->get()
            ->keyBy('key');
    }

    /**
     * Retrieve aggregate values for plotting on a graph.
     */
    public function graph(array $types, Interval $interval)
    {
        $now = new CarbonImmutable;
        $period = $interval->totalSeconds / 60;
        $maxDataPoints = 60;
        $secondsPerPeriod = ($interval->totalSeconds / $maxDataPoints);
        $currentBucket = (int) floor((int) $now->timestamp / $secondsPerPeriod) * $secondsPerPeriod;
        $firstBucket = $currentBucket - (($maxDataPoints - 1) * $secondsPerPeriod);

        $padding = collect()
            ->range(0, 59)
            ->mapWithKeys(fn ($i) => [CarbonImmutable::createFromTimestamp($firstBucket + $i * $secondsPerPeriod)->toDateTimeString() => null]);

        $structure = collect($types)->mapWithKeys(fn ($type) => [$type => $padding]);

        return $this->db->connection()->table('pulse_aggregates')
            ->select(['bucket', 'type', 'key', 'value'])
            ->whereIn('type', $types)
            ->where('period', $period)
            ->where('bucket', '>=', $firstBucket)
            ->orderBy('bucket')
            ->get()
            ->groupBy('key')
            ->map(fn ($readings) => $structure->merge($readings
                ->groupBy('type')
                ->map(fn ($readings) => $padding->merge(
                    $readings->mapWithKeys(function ($reading) {
                        return [CarbonImmutable::createFromTimestamp($reading->bucket)->toDateTimeString() => $reading->value];
                    })
                ))
            ));
    }

    /**
     * Retrieve max aggregate values.
     */
    public function max(
        string $type,
        Interval $interval,
        string $orderBy = 'max',
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $now = new CarbonImmutable;
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('key')
            ->selectRaw('max(`max`) as `max`')
            ->selectRaw('sum(`count`) as `count`')
            ->fromSub(fn (Builder $query) => $query
                // tail
                ->select('key')
                ->selectRaw('max(`value`) as `max`')
                ->selectRaw('count(*) as `count`')
                ->from('pulse_entries')
                ->where('type', $type)
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key')
                    ->selectRaw('max(`value`) as `max`')
                    ->selectRaw('sum(`count`) as `count`')
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', $type.':max')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('key')
            ->orderBy($orderBy, $direction)
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieve sum aggregate values.
     */
    public function sum(
        string $type,
        Interval $interval,
        string $orderBy = 'sum',
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $now = new CarbonImmutable();
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->select('key')
            ->selectRaw('sum(`sum`) as `sum`')
            ->fromSub(fn (Builder $query) => $query
                // tail
                ->select('key')
                ->selectRaw('sum(`value`) as `sum`')
                ->from('pulse_entries')
                ->where('type', $type)
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key')
                    ->selectRaw('sum(`value`) as `sum`')
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->where('type', $type.':sum')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('key')
            ->orderBy($orderBy, $direction)
            ->limit($limit)
            ->get();
    }
}

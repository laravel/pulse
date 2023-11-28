<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Config\Repository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Support\DatabaseConnectionResolver;
use Laravel\Pulse\Value;

/**
 * @phpstan-type AggregateRow array{bucket: int, period: int, type: string, aggregate: string, key: string, value: int, count: int}
 */
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
            (int) CarbonInterval::hour()->totalSeconds / 60,
            (int) CarbonInterval::hours(6)->totalSeconds / 60,
            (int) CarbonInterval::hours(24)->totalSeconds / 60,
            (int) CarbonInterval::days(7)->totalSeconds / 60,
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

        // TODO: Run a single delete with multiple grouped conditions?
        // E.g. where (`period` = 60 AND `bucket` <= 1623072000) or (`period` = 360 AND `bucket` <= 1623046800)
        // 1 query instead of 5

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
     * @param  list<AggregateRow>  $values
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
     * @param  list<AggregateRow>  $values
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
     * @param  list<AggregateRow>  $values
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
     * @param  list<AggregateRow>  $values
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
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     * @param  list<int>  $periods
     * @return \Illuminate\Support\LazyCollection<int, AggregateRow>
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
     *
     * @param  list<string>  $keys
     * @return \Illuminate\Support\Collection<
     *     int,
     *     array<
     *         string,
     *         array{
     *             timestamp: int,
     *             type: string,
     *             key: string,
     *             value: string
     *         }
     *     >
     * >
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
     *
     * @param  list<string>  $types
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, int|null>>>
     */
    public function graph(array $types, string $aggregate, CarbonInterval $interval): Collection
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

        return $this->db->connection()->table('pulse_aggregates') // @phpstan-ignore return.type
            ->select(['bucket', 'type', 'key', 'value'])
            ->whereIn('type', $types)
            ->where('aggregate', $aggregate)
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
     * Retrieve aggregate values for the given type.
     *
     * @param  list<string>  $aggregates
     * @return \Illuminate\Support\Collection<int, object{
     *     key: string,
     *     max?: int,
     *     sum?: int,
     *     avg?: int,
     *     count?: int
     * }>
     */
    public function aggregate(
        string $type,
        array|string $aggregates,
        CarbonInterval $interval,
        string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $aggregates = is_array($aggregates) ? $aggregates : [$aggregates];
        // TODO: Validate `count` isn't included by itself, or figure out a way to make that work.
        // Maybe add it as a separate `includeCount` parameter seeing as it's not an aggregate that is explicitly collected.
        $orderBy ??= $aggregates[0];
        $now = new CarbonImmutable();
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        $query = $this->db->connection()->query()->addSelect('key');

        if (in_array('sum', $aggregates)) {
            $query->selectRaw('sum(`sum`) as `sum`');
        }

        if (in_array('max', $aggregates)) {
            $query->selectRaw('max(`max`) as `max`');
        }

        if (in_array('avg', $aggregates)) {
            $query->selectRaw('avg(`avg`) as `avg`');
        }

        if (in_array('count', $aggregates)) {
            $query->selectRaw('sum(`count`) as `count`');
        }

        return $query->fromSub(function (Builder $query) use ($type, $aggregates, $period, $tailStart, $tailEnd, $oldestBucket) {
            // Tail
            $query->select('key');

            if (in_array('sum', $aggregates)) {
                $query->selectRaw('sum(`value`) as `sum`');
            }

            if (in_array('max', $aggregates)) {
                $query->selectRaw('max(`value`) as `max`');
            }

            if (in_array('avg', $aggregates)) {
                $query->selectRaw('round(avg(`value`)) as `avg`');
            }

            if (in_array('count', $aggregates)) {
                $query->selectRaw('count(*) as `count`');
            }

            $query
                ->from('pulse_entries')
                ->where('type', $type)
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key');

            // Buckets
            $first = true;
            foreach ($aggregates as $currentAggregate) {
                // Alt approach: loop count as well, but set the "aggregate" column to the first non-count one.
                // Note: adds an extra union
                if ($currentAggregate === 'count') {
                    continue;
                }

                $query->unionAll(function (Builder $query) use (&$first, $type, $aggregates, $currentAggregate, $period, $oldestBucket) {
                    $query->select('key');

                    foreach ($aggregates as $aggregate) {
                        if ($aggregate === 'count') {
                            continue;
                        }

                        if ($aggregate === $currentAggregate) {
                            if ($aggregate === 'sum') {
                                $query->selectRaw('sum(`value`) as `sum`');
                            } elseif ($aggregate === 'max') {
                                $query->selectRaw('max(`value`) as `max`');
                            } elseif ($aggregate === 'avg') {
                                $query->selectRaw('avg(`value`) as `avg`');
                            }
                        } else {
                            $query->selectRaw("null as `$aggregate`");
                        }
                    }

                    if (in_array('count', $aggregates)) {
                        if ($first) {
                            $query->selectRaw('sum(`count`) as `count`');
                            $first = false;
                        } else {
                            $query->selectRaw('0 as `count`');
                        }
                    }

                    $query
                        ->from('pulse_aggregates')
                        ->where('period', $period)
                        ->where('type', $type)
                        ->where('aggregate', $currentAggregate)
                        ->where('bucket', '>=', $oldestBucket)
                        ->groupBy('key');
                });
            }
        }, as: 'child')
            ->groupBy('key')
            ->orderBy($orderBy, $direction)
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieve aggregate values for the given types.
     *
     * @param  string|list<string>  $types
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function aggregateTypes(
        string|array $types,
        string $aggregate,
        CarbonInterval $interval,
        string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $types = is_array($types) ? $types : [$types];
        $orderBy ??= $types[0];

        $now = new CarbonImmutable();
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        $query = $this->db->connection()->query()->select('key');

        foreach ($types as $type) {
            $query->selectRaw(match ($aggregate) {
                'sum' => 'sum(`'.$type.'`) as `'.$type.'`',
                'max' => 'max(`'.$type.'`) as `'.$type.'`',
                'avg' => 'avg(`'.$type.'`) as `'.$type.'`',
                default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
            });
        }

        return $query->fromSub(function (Builder $query) use ($types, $aggregate, $tailStart, $tailEnd, $period, $oldestBucket) {
            // Tail
            $query->select('key');

            foreach ($types as $type) {
                $query->selectRaw(match ($aggregate) {
                    'sum' => 'sum(case when (`type` = ?) then `value` else null end) as `'.$type.'`',
                    'max' => 'max(case when (`type` = ?) then `value` else null end) as `'.$type.'`',
                    'avg' => 'avg(case when (`type` = ?) then `value` else null end) as `'.$type.'`',
                    default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                }, [$type]);
            }

            $query
                ->from('pulse_entries')
                ->whereIn('type', $types)
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key');

            $query->unionAll(function (Builder $query) use ($types, $aggregate, $period, $oldestBucket) {
                $query->select('key');

                foreach ($types as $type) {
                    $query->selectRaw(match ($aggregate) {
                        'sum' => 'sum(case when (`type` = ?) then `value` else null end) as `'.$type.'`',
                        'max' => 'max(case when (`type` = ?) then `value` else null end) as `'.$type.'`',
                        'avg' => 'avg(case when (`type` = ?) then `value` else null end) as `'.$type.'`',
                        default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                    }, [$type]);
                }

                $query
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->whereIn('type', $types)
                    ->where('aggregate', $aggregate)
                    ->where('aggregate', 'sum')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key');
            });
        }, as: 'child')
            ->groupBy('key')
            ->orderBy($orderBy, $direction)
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieve an aggregate total for the given types.
     *
     * @param  string|list<string>  $types
     * @return \Illuminate\Support\Collection<string, int>
     */
    public function aggregateTotal(
        array|string $types,
        string $aggregate,
        CarbonInterval $interval,
    ): Collection {
        // TODO: Aggregate can't be 'count'
        $types = is_array($types) ? $types : [$types];

        $now = new CarbonImmutable();
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->addSelect('type')
            ->selectRaw(match ($aggregate) {
                'sum' => 'sum(`sum`) as `sum`',
                'max' => 'max(`max`) as `max`',
                'avg' => 'avg(`avg`) as `avg`',
                default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
            })
            ->fromSub(fn (Builder $query) => $query
                // Tail
                ->addSelect('type')
                ->selectRaw(match ($aggregate) {
                    'sum' => 'sum(`value`) as `sum`',
                    'max' => 'max(`value`) as `max`',
                    'avg' => 'avg(`value`) as `avg`',
                    default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                })
                ->from('pulse_entries')
                ->whereIn('type', $types)
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('type')
                // Buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('type')
                    ->selectRaw(match ($aggregate) {
                        'sum' => 'sum(`value`) as `sum`',
                        'max' => 'max(`value`) as `max`',
                        'avg' => 'avg(`value`) as `avg`',
                        default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                    })
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->whereIn('type', $types)
                    ->where('aggregate', $aggregate)
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('type')
                ), as: 'child'
            )
            ->groupBy('type')
            ->get()
            ->pluck($aggregate, 'type');
    }
}

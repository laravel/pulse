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
            ->aggregateAttributes($entries->filter->isCount(), $periods, 'count')
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->upsertCount($chunk->all()));

        $this
            ->aggregateAttributes($entries->filter->isMax(), $periods, 'max')
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->upsertMax($chunk->all()));

        $this
            ->aggregateAttributes($entries->filter->isAvg(), $periods, 'avg')
            ->chunk($this->config->get('pulse.storage.database.chunk'))
            ->each(fn ($chunk) => $this->upsertAvg($chunk->all()));

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
     * @param  list<string>  $types
     */
    public function purge(array $types = null): void
    {
        if ($types === null) {
            $this->db->connection()->table('pulse_values')->truncate();
            $this->db->connection()->table('pulse_entries')->truncate();
            $this->db->connection()->table('pulse_aggregates')->truncate();

            return;
        }

        $this->db->connection()->table('pulse_values')->whereIn('type', $types)->delete();
        $this->db->connection()->table('pulse_entries')->whereIn('type', $types)->delete();
        $this->db->connection()->table('pulse_aggregates')->whereIn('type', $types)->delete();
    }

    /**
     * Insert new records or update the existing ones and update the count.
     *
     * @param  list<AggregateRow>  $values
     */
    protected function upsertCount(array $values): bool
    {
        return $this->upsert(
            $values,
            'on duplicate key update `value` = `value` + 1'
        );
    }

    /**
     * Insert new records or update the existing ones and the maximum.
     *
     * @param  list<AggregateRow>  $values
     */
    protected function upsertMax(array $values): bool
    {
        return $this->upsert(
            $values,
            'on duplicate key update `value` = greatest(`value`, values(`value`))'
        );
    }

    /**
     * Insert new records or update the existing ones and the average.
     *
     * @param  list<AggregateRow>  $values
     */
    protected function upsertAvg(array $values): bool
    {
        return $this->upsert(
            $values,
            ' on duplicate key update `value` = (`value` * `count` + values(`value`)) / (`count` + 1), `count` = `count` + 1',
        );
    }

    /**
     * Perform an "upsert" query with an "on duplicate key" clause.
     *
     * @param  list<AggregateRow>  $values
     * @param  list<string>  $onDuplicateKeyColumns
     */
    protected function upsert(array $values, string $onDuplicateKeyClause): bool
    {
        $grammar = $this->db->connection()->getQueryGrammar();

        $sql = $grammar->compileInsert(
            $this->db->connection()->table('pulse_aggregates'),
            $values
        );

        $sql .= ' '.$onDuplicateKeyClause;

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
     * @param  'count'|'max'|'avg'  $aggregate
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, int|null>>>
     */
    public function graph(array $types, string $aggregate, CarbonInterval $interval): Collection
    {
        $now = CarbonImmutable::now();
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
     * @param  list<'count'|'max'|'avg'>  $aggregates
     * @return \Illuminate\Support\Collection<int, object{
     *     key: string,
     *     max?: int,
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
        $orderBy ??= $aggregates[0];

        return $this->db->connection()
            ->query()
            ->select([
                'key' => fn (Builder $query) => $query
                    ->select('key')
                    ->from('pulse_entries', as: 'keys')
                    ->whereColumn('keys.key_hash', 'aggregated.key_hash')
                    ->limit(1),
                ...$aggregates,
            ])
            ->fromSub(function (Builder $query) use ($type, $aggregates, $interval, $orderBy, $direction, $limit) {
                $query->select('key_hash');

                foreach ($aggregates as $aggregate) {
                    $query->selectRaw(match ($aggregate) {
                        'count' => 'sum(`count`)',
                        'max' => 'max(`max`)',
                        'avg' => 'avg(`avg`)',
                        default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                    }." as `{$aggregate}`");
                }

                $query->fromSub(function (Builder $query) use ($type, $aggregates, $interval) {
                    $now = CarbonImmutable::now();
                    $period = $interval->totalSeconds / 60;
                    $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
                    $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
                    $oldestBucket = $currentBucket - $interval->totalSeconds + $period;

                    // Tail
                    $query->select('key_hash');

                    foreach ($aggregates as $aggregate) {
                        $query->selectRaw(match ($aggregate) {
                            'count' => 'count(*)',
                            'max' => 'max(`value`)',
                            'avg' => 'avg(`value`)',
                            default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                        }." as `{$aggregate}`");
                    }

                    $query
                        ->from('pulse_entries')
                        ->where('type', $type)
                        ->where('timestamp', '>=', $windowStart)
                        ->where('timestamp', '<=', $oldestBucket - 1)
                        ->groupBy('key_hash');

                    // Buckets
                    foreach ($aggregates as $currentAggregate) {
                        $query->unionAll(function (Builder $query) use ($type, $aggregates, $currentAggregate, $period, $oldestBucket) {
                            $query->select('key_hash');

                            foreach ($aggregates as $aggregate) {
                                if ($aggregate === $currentAggregate) {
                                    $query->selectRaw(match ($aggregate) {
                                        'count' => 'sum(`value`)',
                                        'max' => 'max(`value`)',
                                        'avg' => 'avg(`value`)',
                                        default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                                    }." as `$aggregate`");
                                } else {
                                    $query->selectRaw("null as `$aggregate`");
                                }
                            }

                            $query
                                ->from('pulse_aggregates')
                                ->where('period', $period)
                                ->where('type', $type)
                                ->where('aggregate', $currentAggregate)
                                ->where('bucket', '>=', $oldestBucket)
                                ->groupBy('key_hash');
                        });
                    }
                }, as: 'results')
                    ->groupBy('key_hash')
                    ->orderBy($orderBy, $direction)
                    ->limit($limit);
            }, as: 'aggregated')
            ->get();
    }

    /**
     * Retrieve aggregate values for the given types.
     *
     * @param  string|list<string>  $types
     * @param  'count'|'max'|'avg'  $aggregate
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

        return $this->db->connection()
            ->query()
            ->select([
                'key' => fn (Builder $query) => $query
                    ->select('key')
                    ->from('pulse_entries', as: 'keys')
                    ->whereColumn('keys.key_hash', 'aggregated.key_hash')
                    ->limit(1),
                ...$types,
            ])
            ->fromSub(function (Builder $query) use ($types, $aggregate, $interval, $orderBy, $direction, $limit) {
                $query->select('key_hash');

                foreach ($types as $type) {
                    $query->selectRaw(match ($aggregate) {
                        'count' => 'sum(`'.$type.'`)',
                        'max' => 'max(`'.$type.'`)',
                        'avg' => 'avg(`'.$type.'`)',
                        default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                    }." as `{$type}`");
                }

                $query->fromSub(function (Builder $query) use ($types, $aggregate, $interval) {
                    $now = CarbonImmutable::now();
                    $period = $interval->totalSeconds / 60;
                    $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
                    $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
                    $oldestBucket = $currentBucket - $interval->totalSeconds + $period;

                    // Tail
                    $query->select('key_hash');

                    foreach ($types as $type) {
                        $query->selectRaw(match ($aggregate) {
                            'count' => 'count(case when (`type` = ?) then `value` else null end)',
                            'max' => 'max(case when (`type` = ?) then `value` else null end)',
                            'avg' => 'avg(case when (`type` = ?) then `value` else null end)',
                            default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                        }." as `{$type}`", [$type]);
                    }

                    $query
                        ->from('pulse_entries')
                        ->whereIn('type', $types)
                        ->where('timestamp', '>=', $windowStart)
                        ->where('timestamp', '<=', $oldestBucket - 1)
                        ->groupBy('key_hash');

                    // Buckets
                    $query->unionAll(function (Builder $query) use ($types, $aggregate, $period, $oldestBucket) {
                        $query->select('key_hash');

                        foreach ($types as $type) {
                            $query->selectRaw(match ($aggregate) {
                                'count' => 'sum(case when (`type` = ?) then `value` else null end)',
                                'max' => 'max(case when (`type` = ?) then `value` else null end)',
                                'avg' => 'avg(case when (`type` = ?) then `value` else null end)',
                                default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                            }." as `{$type}`", [$type]);
                        }

                        $query
                            ->from('pulse_aggregates')
                            ->where('period', $period)
                            ->whereIn('type', $types)
                            ->where('aggregate', $aggregate)
                            ->where('bucket', '>=', $oldestBucket)
                            ->groupBy('key_hash');
                    });
                }, as: 'results')
                    ->groupBy('key_hash')
                    ->orderBy($orderBy, $direction)
                    ->limit($limit);
            }, as: 'aggregated')
            ->get();
    }

    /**
     * Retrieve an aggregate total for the given types.
     *
     * @param  string|list<string>  $types
     * @param  'count'|'max'|'avg'  $aggregate
     * @return \Illuminate\Support\Collection<string, int>
     */
    public function aggregateTotal(
        array|string $types,
        string $aggregate,
        CarbonInterval $interval,
    ): Collection {
        $types = is_array($types) ? $types : [$types];

        $now = CarbonImmutable::now();
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / $period) * $period;
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->db->connection()->query()
            ->addSelect('type')
            ->selectRaw(match ($aggregate) {
                'count' => 'sum(`count`)',
                'max' => 'max(`max`)',
                'avg' => 'avg(`avg`)',
                default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
            }." as `{$aggregate}`")
            ->fromSub(fn (Builder $query) => $query
                // Tail
                ->addSelect('type')
                ->selectRaw(match ($aggregate) {
                    'count' => 'count(*)',
                    'max' => 'max(`value`)',
                    'avg' => 'avg(`value`)',
                    default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                }." as `{$aggregate}`")
                ->from('pulse_entries')
                ->whereIn('type', $types)
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('type')
                // Buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('type')
                    ->selectRaw(match ($aggregate) {
                        'count' => 'sum(`value`)',
                        'max' => 'max(`value`)',
                        'avg' => 'avg(`value`)',
                        default => throw new \InvalidArgumentException("Invalid aggregate type [$aggregate]"),
                    }."as `{$aggregate}`")
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

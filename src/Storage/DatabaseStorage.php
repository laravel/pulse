<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Value;

/**
 * @phpstan-type AggregateRow array{bucket: int, period: int, type: string, aggregate: string, key: string, value: int, count: int}
 *
 * @internal
 */
class DatabaseStorage implements Storage
{
    /**
     * Create a new Database storage instance.
     */
    public function __construct(
        protected DatabaseManager $db,
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

        $this->connection()->transaction(function () use ($items) {
            [$entries, $values] = $items->partition(fn (Entry|Value $entry) => $entry instanceof Entry);

            $entries
                ->reject->isOnlyBuckets()
                ->chunk($this->config->get('pulse.storage.database.chunk'))
                ->each(fn ($chunk) => $this->connection()
                    ->table('pulse_entries')
                    ->insert($chunk->map->attributes()->all())
                );

            $periods = [
                (int) (CarbonInterval::hour()->totalSeconds / 60),
                (int) (CarbonInterval::hours(6)->totalSeconds / 60),
                (int) (CarbonInterval::hours(24)->totalSeconds / 60),
                (int) (CarbonInterval::days(7)->totalSeconds / 60),
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
                ->each(fn ($chunk) => $this->connection()
                    ->table('pulse_values')
                    ->upsert(
                        $chunk->map->attributes()->all(),
                        ['type', 'key'],
                        ['timestamp', 'value']
                    )
                );
        });
    }

    /**
     * Trim the storage.
     */
    public function trim(): void
    {
        $now = CarbonImmutable::now();

        $this->connection()
            ->table('pulse_values')
            ->where('timestamp', '<=', $now->subWeek()->getTimestamp())
            ->delete();

        $this->connection()
            ->table('pulse_entries')
            ->where('timestamp', '<=', $now->subWeek()->getTimestamp())
            ->delete();

        // TODO: Run a single delete with multiple grouped conditions?
        // E.g. where (`period` = 60 AND `bucket` <= 1623072000) or (`period` = 360 AND `bucket` <= 1623046800)
        // 1 query instead of 5

        $this->connection()
            ->table('pulse_aggregates')
            ->distinct()
            ->pluck('period')
            ->each(fn (int $period) => $this->connection()
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
            $this->connection()->table('pulse_values')->truncate();
            $this->connection()->table('pulse_entries')->truncate();
            $this->connection()->table('pulse_aggregates')->truncate();

            return;
        }

        $this->connection()->table('pulse_values')->whereIn('type', $types)->delete();
        $this->connection()->table('pulse_entries')->whereIn('type', $types)->delete();
        $this->connection()->table('pulse_aggregates')->whereIn('type', $types)->delete();
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
     */
    protected function upsert(array $values, string $onDuplicateKeyClause): bool
    {
        $grammar = $this->connection()->getQueryGrammar();

        $sql = $grammar->compileInsert(
            $this->connection()->table('pulse_aggregates'),
            $values
        );

        $sql .= ' '.$onDuplicateKeyClause;

        return $this->connection()->statement($sql, Arr::flatten($values, 1));
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
                    if ($entry->timestamp < CarbonImmutable::now()->subMinutes($period)->getTimestamp()) {
                        continue;
                    }

                    yield [
                        'bucket' => (int) (floor($entry->timestamp / $period) * $period),
                        'period' => $period,
                        'type' => $entry->type,
                        'aggregate' => $aggregateSuffix,
                        'key' => $entry->key,
                        'value' => $aggregateSuffix === 'count'
                            ? 1
                            : $entry->value,
                        ...($aggregateSuffix === 'avg')
                            ? ['count' => 1]
                            : [],
                    ];
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
        return $this->connection()
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
        $currentBucket = (int) (floor($now->getTimestamp() / $secondsPerPeriod) * $secondsPerPeriod);
        $firstBucket = $currentBucket - (($maxDataPoints - 1) * $secondsPerPeriod);

        $padding = collect()
            ->range(0, 59)
            ->mapWithKeys(fn ($i) => [CarbonImmutable::createFromTimestamp($firstBucket + $i * $secondsPerPeriod)->toDateTimeString() => null]);

        $structure = collect($types)->mapWithKeys(fn ($type) => [$type => $padding]);

        return $this->connection()->table('pulse_aggregates') // @phpstan-ignore return.type
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
     * @param  'count'|'max'|'avg'|list<'count'|'max'|'avg'>  $aggregates
     * @return \Illuminate\Support\Collection<int, object{
     *     key: string,
     *     max?: int,
     *     avg?: int,
     *     count?: int
     * }>
     */
    public function aggregate(
        string $type,
        string|array $aggregates,
        CarbonInterval $interval,
        string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $aggregates = is_array($aggregates) ? $aggregates : [$aggregates];

        if ($invalid = array_diff($aggregates, $allowed = ['count', 'max', 'avg'])) {
            throw new InvalidArgumentException('Invalid aggregate type(s) ['.implode(', ', $invalid).'], allowed types: ['.implode(', ', $allowed).'].');
        }

        $orderBy ??= $aggregates[0];

        return $this->connection()
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
                    }." as `{$aggregate}`");
                }

                $query->fromSub(function (Builder $query) use ($type, $aggregates, $interval) {
                    $now = CarbonImmutable::now();
                    $period = $interval->totalSeconds / 60;
                    $windowStart = (int) ($now->getTimestamp() - $interval->totalSeconds + 1);
                    $currentBucket = (int) (floor($now->getTimestamp() / $period) * $period);
                    $oldestBucket = $currentBucket - $interval->totalSeconds + $period;

                    // Tail
                    $query->select('key_hash');

                    foreach ($aggregates as $aggregate) {
                        $query->selectRaw(match ($aggregate) {
                            'count' => 'count(*)',
                            'max' => 'max(`value`)',
                            'avg' => 'avg(`value`)',
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
        if (! in_array($aggregate, $allowed = ['count', 'max', 'avg'])) {
            throw new InvalidArgumentException("Invalid aggregate type [$aggregate], allowed types: [".implode(', ', $allowed).'].');
        }

        $types = is_array($types) ? $types : [$types];
        $orderBy ??= $types[0];

        return $this->connection()
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
                    }." as `{$type}`");
                }

                $query->fromSub(function (Builder $query) use ($types, $aggregate, $interval) {
                    $now = CarbonImmutable::now();
                    $period = $interval->totalSeconds / 60;
                    $windowStart = (int) ($now->getTimestamp() - $interval->totalSeconds + 1);
                    $currentBucket = (int) (floor($now->getTimestamp() / $period) * $period);
                    $oldestBucket = $currentBucket - $interval->totalSeconds + $period;

                    // Tail
                    $query->select('key_hash');

                    foreach ($types as $type) {
                        $query->selectRaw(match ($aggregate) {
                            'count' => 'count(case when (`type` = ?) then `value` else null end)',
                            'max' => 'max(case when (`type` = ?) then `value` else null end)',
                            'avg' => 'avg(case when (`type` = ?) then `value` else null end)',
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
        if (! in_array($aggregate, $allowed = ['count', 'max', 'avg'])) {
            throw new InvalidArgumentException("Invalid aggregate type [$aggregate], allowed types: [".implode(', ', $allowed).'].');
        }

        $types = is_array($types) ? $types : [$types];

        $now = CarbonImmutable::now();
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) ($now->getTimestamp() - $interval->totalSeconds + 1);
        $currentBucket = (int) (floor($now->getTimestamp() / $period) * $period);
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->connection()->query()
            ->addSelect('type')
            ->selectRaw(match ($aggregate) {
                'count' => 'sum(`count`)',
                'max' => 'max(`max`)',
                'avg' => 'avg(`avg`)',
            }." as `{$aggregate}`")
            ->fromSub(fn (Builder $query) => $query
                // Tail
                ->addSelect('type')
                ->selectRaw(match ($aggregate) {
                    'count' => 'count(*)',
                    'max' => 'max(`value`)',
                    'avg' => 'avg(`value`)',
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

    /**
     * Resolve the database connection.
     */
    protected function connection(): Connection
    {
        return $this->db->connection($this->config->get('pulse.storage.database.connection'));
    }
}

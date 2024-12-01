<?php

namespace Laravel\Pulse\Storage;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Value;
use RuntimeException;

/**
 * @phpstan-type AggregateRow array{bucket: int, period: int, type: string, aggregate: string, key: string, value: int|float, count?: int}
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

        [$entries, $values] = $items->partition(fn (Entry|Value $entry) => $entry instanceof Entry);

        $entryChunks = $entries
            ->reject->isOnlyBuckets()
            ->when(
                $this->requiresManualKeyHash(),
                fn ($entries) => $entries->map(fn ($entry) => [
                    ...($attributes = $entry->attributes()),
                    'key_hash' => md5($attributes['key']),
                ]),
                fn ($entries) => $entries->map->attributes()
            )
            ->chunk($this->config->get('pulse.storage.database.chunk'));

        [$counts, $minimums, $maximums, $sums, $averages] = array_values($entries
            ->reduce(function ($carry, $entry) {
                foreach ($entry->aggregations() as $aggregation) {
                    $carry[$aggregation][] = $entry;
                }

                return $carry;
            }, ['count' => [], 'min' => [], 'max' => [], 'sum' => [], 'avg' => []])
        );

        $countChunks = $this->preaggregateCounts(collect($counts)) // @phpstan-ignore argument.templateType, argument.templateType
            ->chunk($this->config->get('pulse.storage.database.chunk'));

        $minimumChunks = $this->preaggregateMinimums(collect($minimums)) // @phpstan-ignore argument.templateType, argument.templateType
            ->chunk($this->config->get('pulse.storage.database.chunk'));

        $maximumChunks = $this->preaggregateMaximums(collect($maximums)) // @phpstan-ignore argument.templateType, argument.templateType
            ->chunk($this->config->get('pulse.storage.database.chunk'));

        $sumChunks = $this->preaggregateSums(collect($sums)) // @phpstan-ignore argument.templateType, argument.templateType
            ->chunk($this->config->get('pulse.storage.database.chunk'));

        $averageChunks = $this->preaggregateAverages(collect($averages)) // @phpstan-ignore argument.templateType, argument.templateType
            ->chunk($this->config->get('pulse.storage.database.chunk'));

        $valueChunks = $this // @phpstan-ignore method.nonObject
            ->collapseValues($values)
            ->when(
                $this->requiresManualKeyHash(),
                fn ($values) => $values->map(fn ($value) => [
                    ...($attributes = $value->attributes()),
                    'key_hash' => md5($attributes['key']),
                ]),
                fn ($values) => $values->map->attributes()
            )
            ->chunk($this->config->get('pulse.storage.database.chunk'));

        $this->connection()->transaction(function () use ($entryChunks, $countChunks, $minimumChunks, $maximumChunks, $sumChunks, $averageChunks, $valueChunks) {
            $entryChunks->each(fn ($chunk) => $this->connection()
                ->table('pulse_entries')
                ->insert($chunk->all()));

            $countChunks->each(fn ($chunk) => $this->upsertCount($chunk->all()));

            $minimumChunks->each(fn ($chunk) => $this->upsertMin($chunk->all()));

            $maximumChunks->each(fn ($chunk) => $this->upsertMax($chunk->all()));

            $sumChunks->each(fn ($chunk) => $this->upsertSum($chunk->all()));

            $averageChunks->each(fn ($chunk) => $this->upsertAvg($chunk->all()));

            $valueChunks->each(fn ($chunk) => $this->connection()
                ->table('pulse_values')
                ->upsert($chunk->all(), ['type', 'key_hash'], ['timestamp', 'value'])
            );
        }, 3);
    }

    /**
     * Trim the storage.
     */
    public function trim(): void
    {
        $now = CarbonImmutable::now();

        $keep = $this->config->get('pulse.storage.trim.keep') ?? '7 days';

        $before = $now->subMilliseconds(
            (int) CarbonInterval::fromString($keep)->totalMilliseconds
        );

        if ($now->subDays(7)->isAfter($before)) {
            $before = $now->subDays(7);
        }

        $this->connection()
            ->table('pulse_values')
            ->where('timestamp', '<=', $before->getTimestamp())
            ->delete();

        $this->connection()
            ->table('pulse_entries')
            ->where('timestamp', '<=', $before->getTimestamp())
            ->delete();

        $this->connection()
            ->table('pulse_aggregates')
            ->distinct()
            ->pluck('period')
            ->each(fn (int $period) => $this->connection()
                ->table('pulse_aggregates')
                ->where('period', $period)
                ->where('bucket', '<=', max($now->subMinutes($period)->getTimestamp(), $before->getTimestamp()))
                ->delete());
    }

    /**
     * Purge the storage.
     *
     * @param  list<string>  $types
     */
    public function purge(?array $types = null): void
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
    protected function upsertCount(array $values): int
    {
        return $this->connection()->table('pulse_aggregates')->upsert(
            $values,
            ['bucket', 'period', 'type', 'aggregate', 'key_hash'],
            [
                'value' => match ($driver = $this->connection()->getDriverName()) {
                    'mariadb', 'mysql' => new Expression('`value` + values(`value`)'),
                    'pgsql', 'sqlite' => new Expression(<<<SQL
                        {$this->wrap('pulse_aggregates.value')} + "excluded"."value"
                        SQL),
                    default => throw new RuntimeException("Unsupported database driver [{$driver}]"),
                },
            ]
        );
    }

    /**
     * Insert new records or update the existing ones and the minimum.
     *
     * @param  list<AggregateRow>  $values
     */
    protected function upsertMin(array $values): int
    {
        return $this->connection()->table('pulse_aggregates')->upsert(
            $values,
            ['bucket', 'period', 'type', 'aggregate', 'key_hash'],
            [
                'value' => match ($driver = $this->connection()->getDriverName()) {
                    'mariadb', 'mysql' => new Expression('least(`value`, values(`value`))'),
                    'pgsql' => new Expression(<<<SQL
                        least({$this->wrap('pulse_aggregates.value')}, "excluded"."value")
                        SQL),
                    'sqlite' => new Expression(<<<SQL
                        min({$this->wrap('pulse_aggregates.value')}, "excluded"."value")
                        SQL),
                    default => throw new RuntimeException("Unsupported database driver [{$driver}]"),
                },
            ]
        );
    }

    /**
     * Insert new records or update the existing ones and the maximum.
     *
     * @param  list<AggregateRow>  $values
     */
    protected function upsertMax(array $values): int
    {
        return $this->connection()->table('pulse_aggregates')->upsert(
            $values,
            ['bucket', 'period', 'type', 'aggregate', 'key_hash'],
            [
                'value' => match ($driver = $this->connection()->getDriverName()) {
                    'mariadb', 'mysql' => new Expression('greatest(`value`, values(`value`))'),
                    'pgsql' => new Expression(<<<SQL
                        greatest({$this->wrap('pulse_aggregates.value')}, "excluded"."value")
                        SQL),
                    'sqlite' => new Expression(<<<SQL
                        max({$this->wrap('pulse_aggregates.value')}, "excluded"."value")
                        SQL),
                    default => throw new RuntimeException("Unsupported database driver [{$driver}]"),
                },
            ]
        );
    }

    /**
     * Insert new records or update the existing ones and the sum.
     *
     * @param  list<AggregateRow>  $values
     */
    protected function upsertSum(array $values): int
    {
        return $this->connection()->table('pulse_aggregates')->upsert(
            $values,
            ['bucket', 'period', 'type', 'aggregate', 'key_hash'],
            [
                'value' => match ($driver = $this->connection()->getDriverName()) {
                    'mariadb', 'mysql' => new Expression('`value` + values(`value`)'),
                    'pgsql', 'sqlite' => new Expression(<<<SQL
                        {$this->wrap('pulse_aggregates.value')} + "excluded"."value"
                        SQL),
                    default => throw new RuntimeException("Unsupported database driver [{$driver}]"),
                },
            ]
        );
    }

    /**
     * Insert new records or update the existing ones and the average.
     *
     * @param  list<AggregateRow>  $values
     */
    protected function upsertAvg(array $values): int
    {
        return $this->connection()->table('pulse_aggregates')->upsert(
            $values,
            ['bucket', 'period', 'type', 'aggregate', 'key_hash'],
            match ($driver = $this->connection()->getDriverName()) {
                'mariadb', 'mysql' => [
                    'value' => new Expression('(`value` * `count` + (values(`value`) * values(`count`))) / (`count` + values(`count`))'),
                    'count' => new Expression('`count` + values(`count`)'),
                ],
                'pgsql', 'sqlite' => [
                    'value' => new Expression(<<<SQL
                        ({$this->wrap('pulse_aggregates.value')} * {$this->wrap('pulse_aggregates.count')} + ("excluded"."value" * "excluded"."count")) / ({$this->wrap('pulse_aggregates.count')} + "excluded"."count")
                        SQL),
                    'count' => new Expression(<<<SQL
                        {$this->wrap('pulse_aggregates.count')} + "excluded"."count"
                        SQL),
                ],
                default => throw new RuntimeException("Unsupported database driver [{$driver}]"),
            }
        );
    }

    /**
     * Pre-aggregate entry counts.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     * @return \Illuminate\Support\Collection<int, AggregateRow>
     */
    protected function preaggregateCounts(Collection $entries): Collection
    {
        return $this->preaggregate($entries, 'count', fn ($aggregate) => [
            ...$aggregate,
            'value' => ($aggregate['value'] ?? 0) + 1,
        ]);
    }

    /**
     * Pre-aggregate entry minimums.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     * @return \Illuminate\Support\Collection<int, AggregateRow>
     */
    protected function preaggregateMinimums(Collection $entries): Collection
    {
        return $this->preaggregate($entries, 'min', fn ($aggregate, $entry) => [
            ...$aggregate,
            'value' => ! isset($aggregate['value'])
                ? $entry->value
                : (int) min($aggregate['value'], $entry->value),
        ]);
    }

    /**
     * Pre-aggregate entry maximums.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     * @return \Illuminate\Support\Collection<int, AggregateRow>
     */
    protected function preaggregateMaximums(Collection $entries): Collection
    {
        return $this->preaggregate($entries, 'max', fn ($aggregate, $entry) => [
            ...$aggregate,
            'value' => ! isset($aggregate['value'])
                ? $entry->value
                : (int) max($aggregate['value'], $entry->value),
        ]);
    }

    /**
     * Pre-aggregate entry sums.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     * @return \Illuminate\Support\Collection<int, AggregateRow>
     */
    protected function preaggregateSums(Collection $entries): Collection
    {
        return $this->preaggregate($entries, 'sum', fn ($aggregate, $entry) => [
            ...$aggregate,
            'value' => ($aggregate['value'] ?? 0) + $entry->value,
        ]);
    }

    /**
     * Pre-aggregate entry averages.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     * @return \Illuminate\Support\Collection<int, AggregateRow>
     */
    protected function preaggregateAverages(Collection $entries): Collection
    {
        return $this->preaggregate($entries, 'avg', fn ($aggregate, $entry) => [
            ...$aggregate,
            'value' => ! isset($aggregate['value'])
                ? $entry->value
                : ($aggregate['value'] * $aggregate['count'] + $entry->value) / ($aggregate['count'] + 1),
            'count' => ($aggregate['count'] ?? 0) + 1,
        ]);
    }

    /**
     * Collapse the given values.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Value>  $values
     * @return \Illuminate\Support\Collection<int, \Laravel\Pulse\Value>
     */
    protected function collapseValues(Collection $values): Collection
    {
        return $values->reverse()->unique(fn (Value $value) => [$value->key, $value->type]);
    }

    /**
     * Pre-aggregate entries with a callback.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $entries
     * @return \Illuminate\Support\Collection<int, AggregateRow>
     */
    protected function preaggregate(Collection $entries, string $aggregate, Closure $callback): Collection
    {
        $aggregates = [];

        foreach ($entries as $entry) {
            foreach ($this->periods() as $period) {
                // Exclude entries that would be trimmed.
                if ($entry->timestamp < CarbonImmutable::now()->subMinutes($period)->getTimestamp()) {
                    continue;
                }

                $bucket = (int) (floor($entry->timestamp / $period) * $period);

                $key = $entry->type.':'.$period.':'.$bucket.':'.$entry->key;

                if (! isset($aggregates[$key])) {
                    $aggregates[$key] = $callback([
                        'bucket' => $bucket,
                        'period' => $period,
                        'type' => $entry->type,
                        'aggregate' => $aggregate,
                        'key' => $entry->key,
                    ], $entry);

                    if ($this->requiresManualKeyHash()) {
                        $aggregates[$key]['key_hash'] = md5($entry->key);
                    }
                } else {
                    $aggregates[$key] = $callback($aggregates[$key], $entry);
                }
            }
        }

        return collect(array_values($aggregates));
    }

    /**
     * The periods to aggregate for.
     *
     * @return list<int>
     */
    protected function periods(): array
    {
        return [
            (int) (CarbonInterval::hour()->totalSeconds / 60),
            (int) (CarbonInterval::hours(6)->totalSeconds / 60),
            (int) (CarbonInterval::hours(24)->totalSeconds / 60),
            (int) (CarbonInterval::days(7)->totalSeconds / 60),
        ];
    }

    /**
     * Retrieve values for the given type.
     *
     * @param  list<string>  $keys
     * @return \Illuminate\Support\Collection<string, object{
     *     timestamp: int,
     *     key: string,
     *     value: string
     * }>
     */
    public function values(string $type, ?array $keys = null): Collection
    {
        /** @phpstan-ignore return.type */
        return $this->connection()
            ->table('pulse_values')
            ->select('timestamp', 'key', 'value')
            ->where('type', $type)
            ->when($keys, fn ($query) => $query->whereIn('key', $keys))
            ->get()
            ->keyBy('key');
    }

    /**
     * Retrieve aggregate values for plotting on a graph.
     *
     * @param  list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, int|null>>>
     */
    public function graph(array $types, string $aggregate, CarbonInterval $interval): Collection
    {
        if (! in_array($aggregate, $allowed = ['count', 'min', 'max', 'sum', 'avg'])) {
            throw new InvalidArgumentException("Invalid aggregate type [$aggregate], allowed types: [".implode(', ', $allowed).'].');
        }

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

        return $this->connection()->table('pulse_aggregates')
            ->select(['bucket', 'type', 'key', 'value'])
            ->whereIn('type', $types)
            ->where('aggregate', $aggregate)
            ->where('period', $period)
            ->where('bucket', '>=', $firstBucket)
            ->orderBy('bucket')
            ->get()
            ->groupBy('key')
            ->sortKeys()
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
     * @param  'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     * @return \Illuminate\Support\Collection<int, object{
     *     key: string,
     *     min?: int,
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
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        $aggregates = is_array($aggregates) ? $aggregates : [$aggregates];

        if ($invalid = array_diff($aggregates, $allowed = ['count', 'min', 'max', 'sum', 'avg'])) {
            throw new InvalidArgumentException('Invalid aggregate type(s) ['.implode(', ', $invalid).'], allowed types: ['.implode(', ', $allowed).'].');
        }

        $orderBy ??= $aggregates[0];

        /** @phpstan-ignore return.type */
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
                        'count' => "sum({$this->wrap('count')})",
                        'min' => "min({$this->wrap('min')})",
                        'max' => "max({$this->wrap('max')})",
                        'sum' => "sum({$this->wrap('sum')})",
                        'avg' => "avg({$this->wrap('avg')})",
                    }." as {$this->wrap($aggregate)}");
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
                            'min' => "min({$this->wrap('value')})",
                            'max' => "max({$this->wrap('value')})",
                            'sum' => "sum({$this->wrap('value')})",
                            'avg' => "avg({$this->wrap('value')})",
                        }." as {$this->wrap($aggregate)}");
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
                                        'count' => "sum({$this->wrap('value')})",
                                        'min' => "min({$this->wrap('value')})",
                                        'max' => "max({$this->wrap('value')})",
                                        'sum' => "sum({$this->wrap('value')})",
                                        'avg' => "avg({$this->wrap('value')})",
                                    }." as {$this->wrap($aggregate)}");
                                } else {
                                    $query->selectRaw("null as {$this->wrap($aggregate)}");
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
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function aggregateTypes(
        string|array $types,
        string $aggregate,
        CarbonInterval $interval,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        if (! in_array($aggregate, $allowed = ['count', 'min', 'max', 'sum', 'avg'])) {
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
                        'count' => "sum({$this->wrap($type)})",
                        'min' => "min({$this->wrap($type)})",
                        'max' => "max({$this->wrap($type)})",
                        'sum' => "sum({$this->wrap($type)})",
                        'avg' => "avg({$this->wrap($type)})",
                    }." as {$this->wrap($type)}");
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
                            'count' => "count(case when ({$this->wrap('type')} = ?) then true else null end)",
                            'min' => "min(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                            'max' => "max(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                            'sum' => "sum(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                            'avg' => "avg(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                        }." as {$this->wrap($type)}", [$type]);
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
                                'count' => "sum(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                                'min' => "min(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                                'max' => "max(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                                'sum' => "sum(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                                'avg' => "avg(case when ({$this->wrap('type')} = ?) then {$this->wrap('value')} else null end)",
                            }." as {$this->wrap($type)}", [$type]);
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
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return float|\Illuminate\Support\Collection<string, int>
     */
    public function aggregateTotal(
        array|string $types,
        string $aggregate,
        CarbonInterval $interval,
    ): float|Collection {
        if (! in_array($aggregate, $allowed = ['count', 'min', 'max', 'sum', 'avg'])) {
            throw new InvalidArgumentException("Invalid aggregate type [$aggregate], allowed types: [".implode(', ', $allowed).'].');
        }

        $now = CarbonImmutable::now();
        $period = $interval->totalSeconds / 60;
        $windowStart = (int) ($now->getTimestamp() - $interval->totalSeconds + 1);
        $currentBucket = (int) (floor($now->getTimestamp() / $period) * $period);
        $oldestBucket = $currentBucket - $interval->totalSeconds + $period;
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        return $this->connection()->query() // @phpstan-ignore return.type
            ->when(is_array($types), fn ($query) => $query->addSelect('type'))
            ->selectRaw(match ($aggregate) {
                'count' => "sum({$this->wrap('count')})",
                'min' => "min({$this->wrap('min')})",
                'max' => "max({$this->wrap('max')})",
                'sum' => "sum({$this->wrap('sum')})",
                'avg' => "avg({$this->wrap('avg')})",
            }." as {$this->wrap($aggregate)}")
            ->fromSub(fn (Builder $query) => $query
                // Tail
                ->addSelect('type')
                ->selectRaw(match ($aggregate) {
                    'count' => 'count(*)',
                    'min' => "min({$this->wrap('value')})",
                    'max' => "max({$this->wrap('value')})",
                    'sum' => "sum({$this->wrap('value')})",
                    'avg' => "avg({$this->wrap('value')})",
                }." as {$this->wrap($aggregate)}")
                ->from('pulse_entries')
                ->when(
                    is_array($types),
                    fn ($query) => $query->whereIn('type', $types),
                    fn ($query) => $query->where('type', $types)
                )
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('type')
                // Buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('type')
                    ->selectRaw(match ($aggregate) {
                        'count' => "sum({$this->wrap('value')})",
                        'min' => "min({$this->wrap('value')})",
                        'max' => "max({$this->wrap('value')})",
                        'sum' => "sum({$this->wrap('value')})",
                        'avg' => "avg({$this->wrap('value')})",
                    }." as {$this->wrap($aggregate)}")
                    ->from('pulse_aggregates')
                    ->where('period', $period)
                    ->when(
                        is_array($types),
                        fn ($query) => $query->whereIn('type', $types),
                        fn ($query) => $query->where('type', $types)
                    )
                    ->where('aggregate', $aggregate)
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('type')
                ), as: 'child'
            )
            ->groupBy('type')
            ->when(
                is_array($types),
                fn ($query) => $query->pluck($aggregate, 'type'),
                fn ($query) => (float) $query->value($aggregate)
            );
    }

    /**
     * Resolve the database connection.
     */
    protected function connection(): Connection
    {
        return $this->db->connection($this->config->get('pulse.storage.database.connection'));
    }

    /**
     * Wrap a value in keyword identifiers.
     */
    protected function wrap(string $value): string
    {
        return $this->connection()->getQueryGrammar()->wrap($value);
    }

    /**
     * Determine whether a manually generated key hash is required.
     */
    protected function requiresManualKeyHash(): bool
    {
        return $this->connection()->getDriverName() === 'sqlite';
    }
}

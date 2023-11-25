<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pulse\Support\DatabaseConnectionResolver;
use stdClass;

/**
 * @internal
 */
class Servers
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected DatabaseConnectionResolver $db,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<string, object{
     *     name: string,
     *     cpu_current: int,
     *     cpu: \Illuminate\Support\Collection<string, int|null>,
     *     memory_current: int,
     *     memory_total: int,
     *     memory: \Illuminate\Support\Collection<string, int|null>,
     *     storage: list<object{
     *         directory: string,
     *         total: int,
     *         used: int,
     *     }>|mixed,
     *     updated_at: \Carbon\CarbonImmutable,
     *     recently_reported: bool,
     * }&stdClass>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        $servers = $this->db->connection()
            ->table('pulse_values')
            ->where('type', 'system')
            ->get()
            ->keyBy('key')
            ->map(function ($system) use ($now) {
                $values = json_decode($system->value, flags: JSON_THROW_ON_ERROR);

                return (object) [
                    'name' => (string) $values->name,
                    'cpu_current' => (int) $values->cpu,
                    'cpu' => collect(),
                    'memory_current' => (int) $values->memory_used,
                    'memory_total' => (int) $values->memory_total,
                    'memory' => collect(),
                    'storage' => collect($values->storage), // @phpstan-ignore argument.templateType argument.templateType
                    'updated_at' => $updatedAt = CarbonImmutable::createFromTimestamp($system->timestamp),
                    'recently_reported' => $updatedAt->isAfter($now->subSeconds(30)),
                ];
            });

        $period = $interval->totalSeconds / 60;

        $maxDataPoints = 60;
        $secondsPerPeriod = ($interval->totalSeconds / $maxDataPoints);
        $currentBucket = (int) floor((int) $now->timestamp / $secondsPerPeriod) * $secondsPerPeriod;
        $firstBucket = $currentBucket - (($maxDataPoints - 1) * $secondsPerPeriod);

        $padding = collect()
            ->range(0, 59)
            ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($firstBucket + $i * $secondsPerPeriod)->toDateTimeString() => null]);

        $this->db->connection()->table('pulse_aggregates')
            ->select(['bucket', 'type', 'key', 'value'])
            ->whereIn('type', ['cpu:avg', 'memory:avg'])
            ->where('period', $period)
            ->where('bucket', '>=', $firstBucket)
            ->orderBy('bucket')
            ->get()
            ->groupBy('key')
            ->map(fn ($readings) => $readings
                ->groupBy(fn ($foo) => Str::beforeLast($foo->type, ':'))
                ->map(fn ($readings) => $padding->merge(
                    $readings->mapWithKeys(fn ($row) => [
                        Carbon::createFromTimestamp($row->bucket)->toDateTimeString() => (int) $row->value,
                    ])
                ))
            )
            ->each(function (Collection $readings, string $server) use ($servers) {
                $servers[$server]->cpu = $readings['cpu'] ?? collect([]);
                $servers[$server]->memory = $readings['memory'] ?? collect([]);
            });

        return $servers;
    }
}

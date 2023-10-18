<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pulse\Recorders\SystemStats;
use stdClass;

/**
 * @internal
 */
class Servers
{
    use Concerns\InteractsWithConnection;

    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Repository $config,
        protected DatabaseManager $db,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<string, object{
     *     name: string,
     *     slug: string,
     *     cpu_percent: int,
     *     memory_used: int,
     *     memory_total: int,
     *     storage: list<object{
     *         directory: string,
     *         total: int,
     *         used: int,
     *     }>,
     *     readings: list<object{
     *         date: string,
     *         cpu_percent: int|null,
     *         memory_used: int|null,
     *     }>,
     *     updated_at: \Carbon\CarbonImmutable,
     *     recently_reported: bool,
     * }>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        $maxDataPoints = 60;

        $currentBucket = CarbonImmutable::createFromTimestamp(
            floor($now->getTimestamp() / ($interval->totalSeconds / $maxDataPoints)) * ($interval->totalSeconds / $maxDataPoints)
        );

        $secondsPerPeriod = (int) ($interval->totalSeconds / $maxDataPoints);

        $padding = collect([])
            ->pad(60, null)
            ->map(fn (mixed $value, int $i) => (object) [
                'date' => $currentBucket->subSeconds($i * $secondsPerPeriod)->format('Y-m-d H:i'),
                'cpu_percent' => null,
                'memory_used' => null,
            ])
            ->reverse()
            ->keyBy('date');

        $serverReadings = $this->connection()->query()
            ->select('bucket', 'server')
            ->selectRaw('MAX(`date`) AS `date`')
            ->when(true, fn (Builder $query) => match ($this->config->get('pulse.recorders.'.SystemStats::class.'.graph_aggregation')) {
                'max' => $query
                    ->selectRaw('ROUND(MAX(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(MAX(`memory_used`)) AS `memory_used`'),
                default => $query
                    ->selectRaw('ROUND(AVG(`cpu_percent`)) AS `cpu_percent`')
                    ->selectRaw('ROUND(AVG(`memory_used`)) AS `memory_used`')
            })
            ->fromSub(
                fn (Builder $query) => $query
                    ->from('pulse_system_stats')
                    ->select(['server', 'cpu_percent', 'memory_used', 'date'])
                    // Divide the data into buckets.
                    ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`date`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                    ->where('date', '>', $now->ceilSeconds($interval->totalSeconds / $maxDataPoints)->subSeconds((int) $interval->totalSeconds)),
                'grouped'
            )
            ->groupBy('server', 'bucket')
            ->orderByDesc('bucket')
            ->get()
            ->reverse()
            ->groupBy('server')
            ->map(function (Collection $readings) use ($secondsPerPeriod, $padding) {
                $readings = $readings->keyBy(fn (stdClass $reading) => CarbonImmutable::createFromTimestamp($reading->bucket * $secondsPerPeriod)->format('Y-m-d H:i'));

                return $padding->merge($readings)->values();
            });

        return $this->connection()->table('pulse_system_stats')
            // Get the latest row for every server, even if it hasn't reported in the selected period.
            ->joinSub(
                $this->connection()->table('pulse_system_stats')
                    ->selectRaw('`server`, MAX(`date`) AS `date`')
                    ->groupBy('server'),
                'grouped',
                fn (JoinClause $join) => $join
                    ->on('pulse_system_stats'.'.server', '=', 'grouped.server')
                    ->on('pulse_system_stats'.'.date', '=', 'grouped.date')
            )
            ->get()
            ->map(fn (stdClass $server) => (object) [
                'name' => (string) $server->server,
                'slug' => Str::slug($server->server),
                'cpu_percent' => (int) $server->cpu_percent,
                'memory_used' => (int) $server->memory_used,
                'memory_total' => (int) $server->memory_total,
                'storage' => json_decode($server->storage, flags: JSON_THROW_ON_ERROR),
                'readings' => $serverReadings->get($server->server)?->map(fn (stdClass $reading) => (object) [
                    'date' => CarbonImmutable::parse($reading->date)->format('Y-m-d H:i:s'),
                    'cpu_percent' => $reading->cpu_percent !== null ? (int) $reading->cpu_percent : null,
                    'memory_used' => $reading->memory_used !== null ? (int) $reading->memory_used : null,
                ])->all() ?? [],
                'updated_at' => $updatedAt = CarbonImmutable::parse($server->date),
                'recently_reported' => (bool) $updatedAt->isAfter($now->subSeconds(30)),
            ])
            ->keyBy('slug');
    }
}

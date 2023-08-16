<?php

namespace Laravel\Pulse\Queries\MySql;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

/**
 * @interval
 */
class SlowJobs
{
    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<int, array{job: string, count: int, slowest: int}>
     */
    public function __invoke(Connection $connection, Interval $interval, int $threshold): Collection
    {
        return $connection->table('pulse_jobs')
            ->selectRaw('`job`, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>=', (new CarbonImmutable)->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $threshold)
            ->groupBy('job')
            ->orderByDesc('slowest')
            ->get();
    }
}

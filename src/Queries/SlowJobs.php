<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

/**
 * @interval
 */
class SlowJobs
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Connection $connection,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<int, array{job: string, count: int, slowest: int}>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        return $this->connection->table('pulse_jobs')
            ->selectRaw('`job`, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>=', $now->subSeconds($interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $this->config->get('pulse.slow_job_threshold'))
            ->groupBy('job')
            ->orderByDesc('slowest')
            ->get();
    }
}

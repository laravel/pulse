<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class SlowJobs
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected DatabaseConnectionResolver $db,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        return $this->db->connection()->query()->select([
            'count',
            'slowest',
            'job' => fn (Builder $query) => $query->select('job')
                ->from('pulse_jobs', as: 'child')
                ->whereRaw('`child`.`job_hash` = `parent`.`job_hash`')
                ->limit(1),
        ])->fromSub(fn (Builder $query) => $query->selectRaw('`job_hash`, MAX(`duration`) as `slowest`, COUNT(*) as `count`')
            ->from('pulse_jobs')
            ->where('slow', true)
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('job_hash')
            ->orderByDesc('slowest')
            ->orderByDesc('count')
            ->limit(101), as: 'parent')
            ->get();
    }
}

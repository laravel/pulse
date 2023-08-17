<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

/**
 * @interval
 */
class Exceptions
{
    /**
     * Create a new query instance.
     */
    public function __construct(protected Connection $connection)
    {
        //
    }

    /**
     * Run the query.
     *
     * @param  'last_occurrence'|'count'  $orderBy
     * @return \Illuminate\Support\Collection<int, array{class: string, location: string, count: int, last_occurrence: string}>
     */
    public function __invoke(Interval $interval, string $orderBy): Collection
    {
        $now = new CarbonImmutable;

        return $this->connection->table('pulse_exceptions')
            ->selectRaw('class, location, COUNT(*) AS count, MAX(date) AS last_occurrence')
            ->where('date', '>=', $now->subSeconds($interval->totalSeconds)->toDateTimeString())
            ->groupBy('class', 'location')
            ->orderByDesc($orderBy)
            ->get();
    }
}

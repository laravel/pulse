<?php

namespace Laravel\Pulse\Queries\MySql;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entries\Table;

/**
 * @interval
 */
class Exceptions
{
    /**
     * Run the query.
     *
     * TODO: have these return objects which will make things more flexible moving forward.
     *
     * @param  'last_occurrence'|'count'  $orderBy
     * @return \Illuminate\Support\Collection<int, array{uri: string, count: int, slowest: int}>
     */
    public function __invoke(Connection $connection, Interval $interval, string $orderBy): Collection
    {
        return $connection->table(Table::Exception->value)
            ->selectRaw('class, location, COUNT(*) AS count, MAX(date) AS last_occurrence')
            ->where('date', '>=', (new CarbonImmutable)->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('class', 'location')
            ->orderByDesc($orderBy)
            ->get();
    }
}

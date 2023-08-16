<?php

namespace Laravel\Pulse\Queries\MySql;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entries\Table;

/**
 * @interval
 */
class SlowQueries
{
    /**
     * Run the query.
     *
     * @return \Illuminate\Support\Collection<int, array{uri: string, count: int, slowest: int}>
     */
    public function __invoke(Connection $connection, CarbonInterval $interval, int $threshold): Collection
    {
        return $connection->table(Table::OutgoingRequest->value)
            ->selectRaw('`uri`, COUNT(*) as count, MAX(duration) AS slowest')
            ->where('date', '>=', (new CarbonImmutable)->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $threshold)
            ->groupBy('uri')
            ->orderByDesc('slowest')
            ->get();
    }
}

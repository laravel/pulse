<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;

/**
 * @interval
 */
class CacheInteractions
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
     */
    public function __invoke(Interval $interval): object
    {
        $now = new CarbonImmutable();

        $cacheInteractions = $this->connection->table('pulse_cache_interactions')
            ->selectRaw('COUNT(*) AS count, SUM(CASE WHEN `hit` = TRUE THEN 1 ELSE 0 END) as hits')
            ->where('date', '>=', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->first() ?? (object) ['hits' => 0];

        $cacheInteractions->hits = (int) $cacheInteractions->hits;

        return $cacheInteractions;
    }
}

<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class CacheInteractions
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
     */
    public function __invoke(Interval $interval): object
    {
        $now = new CarbonImmutable();

        return $this->db->connection()
            ->table('pulse_cache_interactions')
            ->selectRaw('COUNT(*) AS `count`, SUM(`hit`) AS `hits`')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->first() ?? (object) ['count' => 0, 'hits' => '0'];
    }
}

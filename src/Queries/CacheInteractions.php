<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Laravel\Pulse\Concerns\InteractsWithDatabaseConnection;

/**
 * @internal
 */
class CacheInteractions
{
    use InteractsWithDatabaseConnection;

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
     */
    public function __invoke(Interval $interval): object
    {
        $now = new CarbonImmutable();

        return $this->db()->table('pulse_cache_interactions')
            ->selectRaw('COUNT(*) AS `count`, SUM(`hit`) AS `hits`')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->first() ?? (object) ['count' => 0, 'hits' => '0'];
    }
}

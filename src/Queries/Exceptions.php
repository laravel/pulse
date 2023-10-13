<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * @internal
 */
class Exceptions
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
     * @param  'last_occurrence'|'count'  $orderBy
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function __invoke(Interval $interval, string $orderBy): Collection
    {
        $now = new CarbonImmutable;

        return $this->connection()->table('pulse_exceptions')
            ->selectRaw('MAX(`class`) AS `class`, MAX(`location`) AS `location`, COUNT(*) AS `count`, MAX(`date`) AS `last_occurrence`')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('class_location_hash')
            ->orderByDesc($orderBy)
            ->get();
    }
}

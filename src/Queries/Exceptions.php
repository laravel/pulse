<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class Exceptions
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
     * @param  'last_occurrence'|'count'  $orderBy
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function __invoke(Interval $interval, string $orderBy): Collection
    {
        $now = new CarbonImmutable;

        return $this->db->connection()
            ->query()
            ->select([
                'count',
                'last_occurrence',
                'class' => fn (Builder $query) => $query->select('class')
                    ->from('pulse_exceptions', as: 'child1')
                    ->whereRaw('`child1`.`class_location_hash` = `parent`.`class_location_hash`')
                    ->limit(1),
                'location' => fn (Builder $query) => $query->select('location')
                    ->from('pulse_exceptions', as: 'child2')
                    ->whereRaw('`child2`.`class_location_hash` = `parent`.`class_location_hash`')
                    ->limit(1),
            ])->fromSub(fn (Builder $query) => $query->selectRaw('`class_location_hash`, MAX(`date`) as `last_occurrence`, COUNT(*) as `count`')
            ->from('pulse_exceptions')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('class_location_hash')
            ->orderByDesc($orderBy)
            ->limit(101), as: 'parent')
            ->get();
    }
}

<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Laravel\Pulse\Recorders\OutgoingRequests;

/**
 * @internal
 */
class SlowOutgoingRequests
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
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        return $this->connection()->query()->select([
            'count',
            'slowest',
            'uri' => fn (Builder $query) => $query->select('uri')
                ->from('pulse_outgoing_requests', as: 'child')
                ->whereRaw('`child`.`uri_hash` = `parent`.`uri_hash`')
                ->limit(1),
        ])->fromSub(fn (Builder $query) => $query->selectRaw('`uri_hash`, MAX(`duration`) as `slowest`, COUNT(*) as `count`')
            ->from('pulse_outgoing_requests')
            ->where('slow', true)
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->groupBy('uri_hash')
            ->orderByDesc('slowest')
            ->orderByDesc('count')
            ->limit(101), as: 'parent')
            ->get();
    }
}

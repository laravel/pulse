<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
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

        return $this->connection()->table('pulse_outgoing_requests')
            ->selectRaw('MAX(`uri`) AS `uri`, COUNT(*) AS `count`, MAX(`duration`) AS `slowest`')
            ->where('date', '>', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->where('duration', '>=', $this->config->get('pulse.recorders.'.OutgoingRequests::class.'.threshold'))
            ->groupBy('uri_hash')
            ->orderByDesc('slowest')
            ->limit(101)
            ->get();
    }
}

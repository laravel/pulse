<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use stdClass;

/**
 * @interval
 */
class MonitoredCacheInteractions
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
     * @param  \Illuminate\Support\Collection<string, string>  $keys
     * @return \Illuminate\Support\Collection<string, object>
     */
    public function __invoke(Interval $interval, Collection $keys): Collection
    {
        if ($keys->isEmpty()) {
            return collect([]);
        }

        $now = new CarbonImmutable();

        $interactions = $keys->mapWithKeys(fn (string $name, string $regex) => [
            $name => (object) [
                'regex' => $regex,
                'key' => $name,
                'uniqueKeys' => 0,
                'hits' => 0,
                'count' => 0,
            ],
        ]);

        $this->connection()->table('pulse_cache_interactions')
            ->selectRaw('`key`, COUNT(*) AS count, SUM(CASE WHEN `hit` = TRUE THEN 1 ELSE 0 END) as hits')
            ->where('date', '>=', $now->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            // TODO: ensure PHP and MySQL regex is compatible
            // TODO modifiers? is redis / memcached / etc case sensitive?
            ->where(fn (Builder $query) => $keys->keys()->each(fn (string $key) => $query->orWhere('key', 'RLIKE', $key)))
            ->orderBy('key')
            ->groupBy('key')
            ->each(function (stdClass $result) use ($interactions, $keys) {
                $name = $keys->firstWhere(fn (string $name, string $regex) => preg_match('/'.$regex.'/', $result->key) > 0);

                if ($name === null) {
                    return;
                }

                $interaction = $interactions[$name];

                $interaction->uniqueKeys++;
                $interaction->hits += $result->hits;
                $interaction->count += $result->count;
            });

        $monitoringIndex = $keys->values()->flip();

        return $interactions->sortBy(fn (stdClass $interaction) => $monitoringIndex[$interaction->key]);
    }
}

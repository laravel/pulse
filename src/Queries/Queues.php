<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Pulse\Support\DatabaseConnectionResolver;

/**
 * @internal
 */
class Queues
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
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, int|null>>>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        $period = $interval->totalSeconds / 60;

        $maxDataPoints = 60;
        $secondsPerPeriod = ($interval->totalSeconds / $maxDataPoints);
        $currentBucket = (int) floor((int) $now->timestamp / $secondsPerPeriod) * $secondsPerPeriod;
        $firstBucket = $currentBucket - (($maxDataPoints - 1) * $secondsPerPeriod);

        $padding = collect()
            ->range(0, 59)
            ->mapWithKeys(fn ($i) => [Carbon::createFromTimestamp($firstBucket + $i * $secondsPerPeriod)->toDateTimeString() => (object) [
                'queued' => null,
                'processing' => null,
                'processed' => null,
                'released' => null,
                'failed' => null,
            ]]);

        return $this->db->connection()->table('pulse_aggregates')
            ->select(['bucket', 'type', 'key', 'value'])
            ->whereIn('type', ['queued:count', 'processing:count', 'processed:count', 'released:count', 'failed:count'])
            ->where('period', $period)
            ->where('bucket', '>=', $firstBucket)
            ->orderBy('bucket')
            ->get()
            ->groupBy('key')
            ->map(fn ($readings) => $padding->merge($readings
                ->groupBy(fn ($row) => Carbon::createFromTimestamp($row->bucket)->toDateTimeString())
                ->map(function ($row) {
                    $row = $row->pluck('value', 'type');

                    return (object) [
                        'queued' => $row['queued:count'] ?? 0,
                        'processing' => $row['processing:count'] ?? 0,
                        'processed' => $row['processed:count'] ?? 0,
                        'released' => $row['released:count'] ?? 0,
                        'failed' => $row['failed:count'] ?? 0,
                    ];
                })
                ->all()
            ));
    }
}

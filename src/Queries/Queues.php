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
 * @internal
 */
class Queues
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
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, object{
     *     date: string,
     *     queued: int,
     *     processing: int,
     *     released: int,
     *     processed: int,
     *     failed: int,
     * }>>
     */
    public function __invoke(Interval $interval): Collection
    {
        $now = new CarbonImmutable;

        $maxDataPoints = 60;

        $currentBucket = CarbonImmutable::createFromTimestamp(
            floor($now->getTimestamp() / ($interval->totalSeconds / $maxDataPoints)) * ($interval->totalSeconds / $maxDataPoints)
        );

        $secondsPerPeriod = (int) ($interval->totalSeconds / $maxDataPoints);

        $padding = collect([])
            ->pad(60, null)
            ->map(fn (mixed $value, int $i) => (object) [
                'date' => $currentBucket->subSeconds($i * $secondsPerPeriod)->format('Y-m-d H:i'),
                'queued' => 0,
                'processing' => 0,
                'released' => 0,
                'processed' => 0,
                'failed' => 0,
            ])
            ->reverse()
            ->keyBy('date');

        $readings = $this->connection()->query()
            ->select('bucket', 'connection', 'queue')
            ->selectRaw('COUNT(`queued_at`) AS `queued`')
            ->selectRaw('COUNT(`processing_at`) AS `processing`')
            ->selectRaw('COUNT(`released_at`) AS `released`')
            ->selectRaw('COUNT(`processed_at`) AS `processed`')
            ->selectRaw('COUNT(`failed_at`) AS `failed`')
            ->fromSub(
                fn (Builder $query) => $query
                    // Queued
                    ->from('pulse_jobs')
                    ->select('connection', 'queue')
                    ->addSelect('queued_at')
                    ->selectRaw('NULL AS `processing_at`')
                    ->selectRaw('NULL AS `released_at`')
                    ->selectRaw('NULL AS `processed_at`')
                    ->selectRaw('NULL AS `failed_at`')
                    // Divide the data into buckets.
                    ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`queued_at`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                    ->where('queued_at', '>=', $now->ceilSeconds($interval->totalSeconds / $maxDataPoints)->subSeconds((int) $interval->totalSeconds))
                    ->whereNotNull('queued_at')
                    // Processing
                    ->union(fn (Builder $query) => $query
                        ->from('pulse_jobs')
                        ->select('connection', 'queue')
                        ->selectRaw('NULL AS `queued_at`')
                        ->addSelect('processing_at')
                        ->selectRaw('NULL AS `released_at`')
                        ->selectRaw('NULL AS `processed_at`')
                        ->selectRaw('NULL AS `failed_at`')
                        // Divide the data into buckets.
                        ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`processing_at`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                        ->where('processing_at', '>=', $now->ceilSeconds($interval->totalSeconds / $maxDataPoints)->subSeconds((int) $interval->totalSeconds))
                        ->whereNotNull('processing_at')
                    )
                    // Released
                    ->union(fn (Builder $query) => $query
                        ->from('pulse_jobs')
                        ->select('connection', 'queue')
                        ->selectRaw('NULL AS `queued_at`')
                        ->selectRaw('NULL AS `processing_at`')
                        ->addSelect('released_at')
                        ->selectRaw('NULL AS `processed_at`')
                        ->selectRaw('NULL AS `failed_at`')
                        // Divide the data into buckets.
                        ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`released_at`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                        ->where('released_at', '>=', $now->ceilSeconds($interval->totalSeconds / $maxDataPoints)->subSeconds((int) $interval->totalSeconds))
                        ->whereNotNull('released_at')
                    )
                    // Processed
                    ->union(fn (Builder $query) => $query
                        ->from('pulse_jobs')
                        ->select('connection', 'queue')
                        ->selectRaw('NULL AS `queued_at`')
                        ->selectRaw('NULL AS `processing_at`')
                        ->selectRaw('NULL AS `released_at`')
                        ->addSelect('processed_at')
                        ->selectRaw('NULL AS `failed_at`')
                        // Divide the data into buckets.
                        ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`processed_at`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                        ->where('processed_at', '>=', $now->ceilSeconds($interval->totalSeconds / $maxDataPoints)->subSeconds((int) $interval->totalSeconds))
                        ->whereNotNull('processed_at')
                    )
                    // Failed
                    ->union(fn (Builder $query) => $query
                        ->from('pulse_jobs')
                        ->select('connection', 'queue')
                        ->selectRaw('NULL AS `queued_at`')
                        ->selectRaw('NULL AS `processing_at`')
                        ->selectRaw('NULL AS `released_at`')
                        ->selectRaw('NULL AS `processed_at`')
                        ->addSelect('failed_at')
                        // Divide the data into buckets.
                        ->selectRaw('FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`failed_at`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                        ->where('failed_at', '>=', $now->ceilSeconds($interval->totalSeconds / $maxDataPoints)->subSeconds((int) $interval->totalSeconds))
                        ->whereNotNull('failed_at')
                    ),
                'grouped'
            )
            ->groupBy('connection', 'queue', 'bucket')
            ->orderByDesc('bucket')
            ->get()
            ->reverse()
            ->groupBy(fn ($value) => "{$value->connection}:{$value->queue}")
            ->sortKeys()
            ->map(function (Collection $readings) use ($secondsPerPeriod, $padding) {
                $readings = $readings
                    ->mapWithKeys(function (stdClass $reading) use ($secondsPerPeriod) {
                        $date = CarbonImmutable::createFromTimestamp($reading->bucket * $secondsPerPeriod)->format('Y-m-d H:i');

                        return [$date => (object) [
                            'date' => $date,
                            'queued' => (int) $reading->queued,
                            'processing' => (int) $reading->processing,
                            'released' => (int) $reading->released,
                            'processed' => (int) $reading->processed,
                            'failed' => (int) $reading->failed,
                        ]];
                    });

                return $padding->merge($readings)->values(); // @phpstan-ignore argument.type
            });

        return $readings;
    }
}

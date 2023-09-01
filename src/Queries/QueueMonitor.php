<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;

class QueueMonitor
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
     */
    public function __invoke(Interval $interval, string $queue, string $connection = null)
    {
        $now = new CarbonImmutable();

        $connection ??= $this->config->get('queue.default');

        $maxDataPoints = 60;

        $currentBucket = CarbonImmutable::createFromTimestamp(
            floor($now->getTimestamp() / ($interval->totalSeconds / $maxDataPoints)) * ($interval->totalSeconds / $maxDataPoints)
        );

        $secondsPerPeriod = (int) ($interval->totalSeconds / $maxDataPoints);

        $padding = collect([])
            ->pad(60, null)
            ->map(fn ($value, $i) => (object) [
                'date' => $currentBucket->subSeconds($i * $secondsPerPeriod)->format('Y-m-d H:i'),
                'size' => null,
                'failed' => null,
            ])
            ->reverse()
            ->keyBy('date');

        return $this->connection()
            ->query()
            ->selectRaw('bucket, MAX(`size`) AS `size`, MAX(`failed`) AS `failed`')
            ->fromSub(
                fn ($query) => $query
                    ->from('pulse_queue_sizes')
                    ->selectRaw('size, failed, date, FLOOR(UNIX_TIMESTAMP(CONVERT_TZ(`date`, ?, @@session.time_zone)) / ?) AS `bucket`', [$now->format('P'), $secondsPerPeriod])
                    ->where('date', '>=', $now->ceilSeconds($interval->totalSeconds / $maxDataPoints)->subSeconds((int) $interval->totalSeconds))
                    ->where('queue', $queue)
                    ->when($connection, fn ($query) => $query->where('connection', $connection)),
                'grouped'
            )
            ->groupBy('bucket')
            ->orderByDesc('bucket')
            ->get()
            ->reverse()
            ->pipe(function ($readings) use ($secondsPerPeriod, $padding) {
                $readings = $readings->keyBy(fn ($reading) => CarbonImmutable::createFromTimestamp($reading->bucket * $secondsPerPeriod)->format('Y-m-d H:i'));

                $readings = $padding->merge($readings)->values();

                $previousFailed = 0;

                return $readings->each(function ($reading) use (&$previousFailed) {
                    if ($reading->failed === null) {
                        $previousFailed = 0;

                        return;
                    }

                    if ($previousFailed === 0 || $previousFailed > $reading->failed) {
                        $previousFailed = $reading->failed;

                        $reading->failed = null;

                        return;
                    }

                    with($reading->failed, function ($newPreviousFailed) use ($reading, &$previousFailed) {
                        $reading->failed = $reading->failed - $previousFailed;

                        $previousFailed = $newPreviousFailed;
                    });
                });
            })
            ->pipe(fn ($readings) => (object) [
                'readings' => $readings->map(fn ($reading) => (object) [
                    'size' => $reading->size,
                    'failed' => $reading->failed,
                ])->all(),
            ]);
    }
}
